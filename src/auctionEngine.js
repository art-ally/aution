const crypto = require('crypto');
const { DatabaseEngine } = require('./db');
const {
  format_money,
  minimum_next_bid,
  strtotime,
  gmdate,
  isEmpty,
} = require('./helpers');

class AuctionEngineService {
  static synchronizeAuctions() {
    const db = DatabaseEngine.getInstance();
    const now = Math.floor(Date.now() / 1000);
    let closedCount = 0;

    const auctions = db.all('auctions');
    let needsTransition = false;
    for (const a of auctions) {
      const status = a.status || '';
      const startsAt = strtotime(a.starts_at || '');
      const endsAt = strtotime(a.ends_at || '');
      if (status === 'upcoming' && startsAt && startsAt <= now) {
        needsTransition = true;
        break;
      }
      if (status === 'active' && endsAt && endsAt <= now) {
        needsTransition = true;
        break;
      }
    }

    if (!needsTransition) {
      return 0;
    }

    db.transaction((txDb) => {
      const allAuctions = txDb.all('auctions');
      const settings = txDb.find('platform_settings', 1) || {};
      const commissionRate = Number(settings.commission_rate_percent ?? 15.0);
      const buyerPremiumRate = Number(settings.buyer_premium_percent ?? 20.0);

      for (const auction of allAuctions) {
        const status = auction.status || '';
        const startsAt = strtotime(auction.starts_at || '');
        const endsAt = strtotime(auction.ends_at || '');

        if (status === 'upcoming' && startsAt <= now && (!endsAt || endsAt > now)) {
          txDb.update('auctions', auction.id, { status: 'active' });
          continue;
        }

        if (status === 'active' && endsAt && endsAt <= now) {
          AuctionEngineService.closeSingleAuction(txDb, auction, commissionRate, buyerPremiumRate);
          closedCount++;
        }
      }
    });

    return closedCount;
  }

  static closeSingleAuction(db, auction, commissionRate = 15.0, buyerPremiumRate = 20.0) {
    const artwork = db.find('artworks', Number(auction.artwork_id)) || {};
    const bids = db.where('bids', (b) => Number(b.auction_id) === Number(auction.id));

    bids.sort((a, b) => {
      const diff = Number(b.amount) - Number(a.amount);
      if (diff !== 0) return diff;
      return String(a.created_at || '').localeCompare(String(b.created_at || ''));
    });

    const winningBid = bids[0] || null;
    const effectiveBidAmount = Number(winningBid?.amount ?? auction.current_bid ?? 0);
    const hasQualifyingBid = winningBid !== null || Number(auction.bid_count || 0) > 0;
    const reservePrice = Number(auction.reserve_price || 0);
    const reserveMet = hasQualifyingBid && effectiveBidAmount >= reservePrice;
    const sub = db.findBy('artwork_submissions', 'artwork_id', Number(auction.artwork_id));

    if (hasQualifyingBid && reserveMet) {
      const hammerPrice = effectiveBidAmount;
      const winnerId = Number(winningBid?.user_id || auction.highest_bidder_id || 6);
      const artistId = Number(auction.artist_id);

      const updatedAuction = db.update('auctions', auction.id, {
        status: 'closed_sold',
        current_bid: hammerPrice,
        highest_bidder_id: winnerId,
        reserve_met: true,
        closed_at: gmdate('Y-m-d H:i:s'),
      });

      db.update('artworks', auction.artwork_id, {
        status: 'sold',
        current_bid: hammerPrice,
      });

      if (sub) {
        db.update('artwork_submissions', sub.id, {
          status: 'closed_sold',
          current_bid: hammerPrice,
        });
      }

      const existingOrder = db.findBy('orders', 'auction_id', Number(auction.id));
      if (!existingOrder) {
        const buyerPremium = Math.round(hammerPrice * (buyerPremiumRate / 100) * 100) / 100;
        const shippingFee = 450.0;
        const totalAmount = Math.round((hammerPrice + buyerPremium + shippingFee) * 100) / 100;
        const sellerCommission = Math.round(hammerPrice * (commissionRate / 100) * 100) / 100;
        const netArtistPayout = Math.round((hammerPrice - sellerCommission) * 100) / 100;
        const randNum = Math.floor(1000 + Math.random() * 9000);

        const order = db.insert('orders', {
          order_number: `ORD-${gmdate('Y')}-${randNum}`,
          auction_id: Number(auction.id),
          artwork_id: Number(auction.artwork_id),
          buyer_id: winnerId,
          artist_id: artistId,
          hammer_price: hammerPrice,
          buyer_premium: buyerPremium,
          shipping_fee: shippingFee,
          total_amount: totalAmount,
          seller_commission_rate: commissionRate,
          seller_commission_amount: sellerCommission,
          net_artist_payout: netArtistPayout,
          currency: auction.currency || 'USD',
          payment_status: 'pending',
          fulfillment_status: 'awaiting_payment',
          payment_deadline: gmdate('Y-m-d H:i:s', strtotime('+7 days')),
        });

        db.insert('commissions', {
          order_id: order.id,
          artist_id: artistId,
          hammer_price: hammerPrice,
          commission_rate_percent: commissionRate,
          commission_amount: sellerCommission,
          buyer_premium_amount: buyerPremium,
          currency: auction.currency || 'USD',
        });

        db.insert('seller_payouts', {
          order_id: order.id,
          artist_id: artistId,
          gross_sale_amount: hammerPrice,
          commission_deducted: sellerCommission,
          net_payout_amount: netArtistPayout,
          currency: auction.currency || 'USD',
          status: 'pending',
          payout_reference: `PO-PENDING-${String(order.id).padStart(4, '0')}`,
          approved_by: null,
        });

        const title = artwork.title || 'Auction Lot';
        db.insert('notifications', {
          user_id: winnerId,
          type: 'auction_won',
          title: `Auction Won: ${title}`,
          message: `Congratulations. Your winning hammer bid of ${format_money(hammerPrice)} secured "${title}". Please complete settlement in your Collector Orders dashboard.`,
          action_url: '/buyer/orders',
          read_at: null,
        });

        db.insert('notifications', {
          user_id: artistId,
          type: 'artwork_sold',
          title: `Artwork Sold at Auction: ${title}`,
          message: `Your work "${title}" closed at a hammer price of ${format_money(hammerPrice)}. Order ${order.order_number} has been generated.`,
          action_url: '/artist/sales',
          read_at: null,
        });
      }

      return updatedAuction || auction;
    }

    const updatedAuction = db.update('auctions', auction.id, {
      status: 'closed_unsold',
      reserve_met: false,
      closed_at: gmdate('Y-m-d H:i:s'),
    });

    if (sub) {
      db.update('artwork_submissions', sub.id, {
        status: 'closed_unsold',
      });
    }

    const title = artwork.title || 'Auction Lot';
    db.insert('notifications', {
      user_id: Number(auction.artist_id),
      type: 'auction_unsold',
      title: `Auction Concluded (Unsold): ${title}`,
      message:
        !hasQualifyingBid
          ? `The timed auction countdown for "${title}" completed with no bids placed.`
          : `The timed auction countdown for "${title}" completed below the confidential reserve price.`,
      action_url: '/artist/dashboard',
      read_at: null,
    });

    return updatedAuction || auction;
  }

  static placeBid(auctionId, userId, amount, ipAddr = '127.0.0.1') {
    const db = DatabaseEngine.getInstance();

    return db.transaction((txDb) => {
      const user = txDb.find('users', userId);
      if (!user || (user.status || 'active') !== 'active') {
        throw new Error('Your collector account is not eligible to place bids.');
      }

      const settings = txDb.find('platform_settings', 1) || {};
      if (!isEmpty(settings.require_email_verification_for_bidding) && isEmpty(user.email_verified_at)) {
        throw new Error('Please verify your email address before placing a bid.');
      }

      const auction = txDb.find('auctions', auctionId);
      if (!auction) {
        throw new Error('The requested auction lot does not exist.');
      }

      const artwork = txDb.find('artworks', Number(auction.artwork_id));
      if (!artwork || (artwork.status || '') !== 'published') {
        throw new Error('This artwork is not currently open for public bidding.');
      }

      if (Number(auction.artist_id) === Number(userId) || Number(artwork.artist_id) === Number(userId)) {
        throw new Error('Institutional policy prohibits artists from bidding on their own works.');
      }

      const now = Math.floor(Date.now() / 1000);
      const startsAt = strtotime(auction.starts_at);
      const endsAt = strtotime(auction.ends_at);

      if ((auction.status || '') !== 'active' || (startsAt && startsAt > now)) {
        throw new Error('This auction is not currently open for bidding.');
      }

      if (endsAt && endsAt <= now) {
        AuctionEngineService.closeSingleAuction(txDb, auction);
        throw new Error('Bidding has closed for this lot.');
      }

      const minNextBid = minimum_next_bid(auction);
      if (amount < minNextBid) {
        throw new Error(
          `Minimum valid bid for this lot is ${format_money(minNextBid, auction.currency || 'USD')}.`
        );
      }

      const previousHighestBidderId = auction.highest_bidder_id ? Number(auction.highest_bidder_id) : null;
      const paddle = `PB-${String(1000 + ((Number(userId) * 791) % 8999)).padStart(4, '0')}`;

      const bid = txDb.insert('bids', {
        auction_id: Number(auctionId),
        artwork_id: Number(auction.artwork_id),
        user_id: Number(userId),
        paddle_number: paddle,
        amount: Math.round(amount * 100) / 100,
        currency: auction.currency || 'USD',
        ip_hash: crypto.createHash('sha256').update(String(ipAddr)).digest('hex'),
        is_seeded: false,
      });

      let newEndsAt = auction.ends_at;
      let extended = false;
      const antiSnipingEnabled =
        (auction.anti_sniping_enabled ?? true) && (settings.anti_sniping_enabled ?? true);
      const windowSeconds = Number(settings.anti_sniping_window_seconds || 300);
      const extensionSeconds = Number(settings.anti_sniping_extension_seconds || 300);

      if (antiSnipingEnabled && endsAt && endsAt - now <= windowSeconds) {
        newEndsAt = gmdate('Y-m-d H:i:s', now + extensionSeconds);
        extended = true;
      }

      const reserveMet = amount >= Number(auction.reserve_price || 0);
      const updatedAuction = txDb.update('auctions', auctionId, {
        current_bid: Math.round(amount * 100) / 100,
        bid_count: Number(auction.bid_count || 0) + 1,
        highest_bidder_id: Number(userId),
        reserve_met: reserveMet,
        ends_at: newEndsAt,
      });

      const inWatchlist = txDb.where(
        'watchlists',
        (w) => Number(w.user_id) === Number(userId) && Number(w.artwork_id) === Number(artwork.id)
      );
      if (inWatchlist.length === 0) {
        txDb.insert('watchlists', {
          user_id: Number(userId),
          artwork_id: Number(artwork.id),
        });
      }

      txDb.insert('notifications', {
        user_id: Number(userId),
        type: 'bid_confirmed',
        title: `Bid Confirmed: ${artwork.title}`,
        message: `Your bid of ${format_money(amount)} (Paddle ${paddle}) is now the leading bid on "${artwork.title}".`,
        action_url: `/artworks/${artwork.slug}`,
        read_at: null,
      });

      if (previousHighestBidderId !== null && previousHighestBidderId !== Number(userId)) {
        txDb.insert('notifications', {
          user_id: previousHighestBidderId,
          type: 'outbid_alert',
          title: `Outbid Alert: ${artwork.title}`,
          message: `Another collector has placed a higher bid of ${format_money(amount)} on "${artwork.title}". Place a new bid to regain the lead.`,
          action_url: `/artworks/${artwork.slug}`,
          read_at: null,
        });
      }

      return {
        bid,
        auction: updatedAuction,
        extended,
        reserve_met: reserveMet,
      };
    });
  }
}

module.exports = { AuctionEngineService };
