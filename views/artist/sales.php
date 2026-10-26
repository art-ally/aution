<div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-12 space-y-12">
    <div class="border-b border-hairline pb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <p class="text-[11px] uppercase tracking-widest text-bronze font-semibold mb-1">Financial Settlement &amp; Disbursements</p>
            <h1 class="font-serif text-4xl text-obsidian">Completed Sales, Commissions &amp; Seller Payouts</h1>
        </div>
        <a href="/artist/dashboard" class="text-xs uppercase tracking-widest text-stone hover:text-obsidian">← Studio Dashboard</a>
    </div>

    <!-- Seller Payouts Table -->
    <div class="bg-white border border-hairline p-8">
        <h2 class="font-serif text-2xl text-obsidian mb-6 border-b border-hairline pb-3">Artist Wire Payout Ledger</h2>
        <?php if (empty($payouts)): ?>
            <p class="text-xs text-stone">No seller payouts recorded yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-hairline text-[10px] uppercase tracking-widest text-stone">
                            <th class="py-3 pr-4">Payout Reference</th>
                            <th class="py-3 pr-4">Gross Hammer Sale</th>
                            <th class="py-3 pr-4">Platform Commission</th>
                            <th class="py-3 pr-4">Net Artist Payout</th>
                            <th class="py-3 pr-4">Status</th>
                            <th class="py-3">Disbursement Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-hairline">
                        <?php foreach ($payouts as $po): ?>
                            <tr>
                                <td class="py-4 pr-4 font-mono font-semibold text-obsidian"><?= e($po['payout_reference']) ?></td>
                                <td class="py-4 pr-4"><?= format_money_exact($po['gross_sale_amount'], $po['currency'] ?? 'USD') ?></td>
                                <td class="py-4 pr-4 text-stone">- <?= format_money_exact($po['commission_deducted'], $po['currency'] ?? 'USD') ?></td>
                                <td class="py-4 pr-4 font-serif text-lg font-semibold text-bronze"><?= format_money_exact($po['net_payout_amount'], $po['currency'] ?? 'USD') ?></td>
                                <td class="py-4 pr-4">
                                    <span class="px-2.5 py-1 text-[10px] uppercase tracking-widest font-semibold <?= ($po['status'] ?? '') === 'completed' ? 'bg-[#1A2E22] text-ivory' : 'bg-alabaster text-obsidian border border-hairline' ?>">
                                        <?= e(strtoupper($po['status'] ?? 'pending')) ?>
                                    </span>
                                </td>
                                <td class="py-4 text-stone"><?= e($po['paid_at'] ?? 'Awaiting Admin Release') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
