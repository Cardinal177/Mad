CREATE DATABASE IF NOT EXISTS `cardinal_mad`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `cardinal_mad`;

CREATE TABLE IF NOT EXISTS households (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  initials VARCHAR(10) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  phone_e164 VARCHAR(25) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_platform_admin TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_initials_phone (initials, phone_e164)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS household_users (
  household_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  role ENUM('owner', 'admin', 'member') NOT NULL DEFAULT 'member',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (household_id, user_id),
  CONSTRAINT fk_household_users_household
    FOREIGN KEY (household_id) REFERENCES households(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_household_users_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS household_locations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_household_location_name (household_id, name),
  CONSTRAINT fk_locations_household
    FOREIGN KEY (household_id) REFERENCES households(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  barcode VARCHAR(64) DEFAULT NULL,
  name VARCHAR(200) NOT NULL,
  brand VARCHAR(120) DEFAULT NULL,
  weight_grams INT UNSIGNED DEFAULT NULL,
  image_url VARCHAR(500) DEFAULT NULL,
  nutrition_json JSON DEFAULT NULL,
  nutrition_source ENUM('unknown', 'off_label', 'frida_dtu', 'manual', 'placeholder') NOT NULL DEFAULT 'unknown',
  nutrition_confidence DECIMAL(4,3) DEFAULT NULL,
  frida_food_code VARCHAR(64) DEFAULT NULL,
  nutrition_updated_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_products_barcode (barcode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS household_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
  minimum_quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inventory (household_id, location_id, product_id),
  CONSTRAINT fk_inventory_household
    FOREIGN KEY (household_id) REFERENCES households(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_inventory_location
    FOREIGN KEY (location_id) REFERENCES household_locations(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_inventory_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  location_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED DEFAULT NULL,
  movement_type ENUM('in', 'out', 'adjust') NOT NULL,
  quantity_delta DECIMAL(10,2) NOT NULL,
  source ENUM('esp32', 'manual', 'import', 'recipe') NOT NULL DEFAULT 'manual',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_movements_household
    FOREIGN KEY (household_id) REFERENCES households(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_movements_location
    FOREIGN KEY (location_id) REFERENCES household_locations(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_movements_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE RESTRICT,
  CONSTRAINT fk_movements_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL,
  INDEX idx_movements_household_created (household_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_household_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT DEFAULT NULL,
  source_type ENUM('manual', 'pdf_upload', 'external_api') NOT NULL DEFAULT 'manual',
  source_reference VARCHAR(500) DEFAULT NULL,
  is_shared TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_recipes_household
    FOREIGN KEY (owner_household_id) REFERENCES households(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipe_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  ingredient_name VARCHAR(200) NOT NULL,
  quantity DECIMAL(10,2) DEFAULT NULL,
  unit VARCHAR(40) DEFAULT NULL,
  CONSTRAINT fk_recipe_ingredients_recipe
    FOREIGN KEY (recipe_id) REFERENCES recipes(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_recipe_ingredients_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_lists (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  household_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  status ENUM('open', 'in_progress', 'done') NOT NULL DEFAULT 'open',
  created_by_user_id BIGINT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_shopping_lists_household
    FOREIGN KEY (household_id) REFERENCES households(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_shopping_lists_user
    FOREIGN KEY (created_by_user_id) REFERENCES users(id)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_list_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  shopping_list_id BIGINT UNSIGNED NOT NULL,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  product_name VARCHAR(200) NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
  unit VARCHAR(40) DEFAULT NULL,
  preferred_store VARCHAR(120) DEFAULT NULL,
  product_category VARCHAR(120) DEFAULT NULL,
  is_checked TINYINT(1) NOT NULL DEFAULT 0,
  offer_price DECIMAL(10,2) DEFAULT NULL,
  offer_valid_until DATE DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_shopping_items_list
    FOREIGN KEY (shopping_list_id) REFERENCES shopping_lists(id)
    ON DELETE CASCADE,
  CONSTRAINT fk_shopping_items_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL,
  INDEX idx_shopping_items_store_category (preferred_store, product_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_offers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_name VARCHAR(100) NOT NULL,
  product_id BIGINT UNSIGNED DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  valid_from DATE DEFAULT NULL,
  valid_to DATE DEFAULT NULL,
  source_url VARCHAR(500) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_offers_product
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE SET NULL,
  INDEX idx_offers_store_dates (store_name, valid_from, valid_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_otp_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  challenge_id VARCHAR(64) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  purpose ENUM('login') NOT NULL DEFAULT 'login',
  code_hash VARCHAR(255) NOT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
  requested_ip VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  sent_via ENUM('sms') NOT NULL DEFAULT 'sms',
  sent_ok TINYINT(1) NOT NULL DEFAULT 0,
  provider_ref VARCHAR(120) DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_challenge_id (challenge_id),
  INDEX idx_auth_challenge_user_created (user_id, created_at),
  INDEX idx_auth_challenge_expires (expires_at),
  CONSTRAINT fk_auth_challenge_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME DEFAULT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  UNIQUE KEY uq_auth_sessions_token_hash (token_hash),
  INDEX idx_auth_sessions_user (user_id),
  INDEX idx_auth_sessions_expires (expires_at),
  CONSTRAINT fk_auth_sessions_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
