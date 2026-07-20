-- Számlán is legyen tetszőleges szállítási és fizetési költség, hogy egy
-- rendelésből készült számla összege megegyezhessen a rendelés végösszegével.
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(14, 2) NOT NULL DEFAULT 0 AFTER total_amount;
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS payment_cost DECIMAL(14, 2) NOT NULL DEFAULT 0 AFTER shipping_cost;
