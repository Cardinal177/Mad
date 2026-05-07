-- Migration: ingredient meta - product_type, location_type, expires_at
-- Date: 2026-05-07

-- 1. Produkttype på produkter
ALTER TABLE products
    ADD COLUMN IF NOT EXISTS product_type ENUM(
        'tørvare',
        'ferskvare',
        'mejeri',
        'kød',
        'fisk',
        'frugt_groent',
        'frostvare',
        'krydderier',
        'drikke',
        'konserves',
        'brød',
        'andet'
    ) NOT NULL DEFAULT 'andet';

-- 2. Lokationstype på lokationer
ALTER TABLE household_locations
    ADD COLUMN IF NOT EXISTS location_type ENUM('dry', 'fridge', 'freezer', 'counter', 'other') NOT NULL DEFAULT 'dry';

-- 3. Udløbsdato på bevægelser (per ind-scanning, ikke statisk på produktet)
ALTER TABLE inventory_movements
    ADD COLUMN IF NOT EXISTS expires_at DATE DEFAULT NULL;
