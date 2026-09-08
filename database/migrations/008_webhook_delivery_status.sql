ALTER TABLE `{{prefix}}webhook_deliveries`
    ADD COLUMN `delivery_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    ADD KEY `webhook_delivery_status_index` (`delivery_status`, `created_at`);

UPDATE `{{prefix}}webhook_deliveries` SET delivery_status='delivered' WHERE delivered_at IS NOT NULL;
