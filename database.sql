-- ========================================================
-- DATABASE SCHEMA: MYFINANCE — Personal Finance Tracker
-- Versi: 1.0.0
-- Karakter: utf8mb4 / utf8mb4_unicode_ci
-- ========================================================

CREATE DATABASE IF NOT EXISTS `myfinance_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `myfinance_db`;

-- 1. TABEL PENGGUNA (USERS)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `initial_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'IDR',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. TABEL REKENING & DOMPET (ACCOUNTS)
CREATE TABLE IF NOT EXISTS `accounts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('cash', 'bank', 'ewallet', 'savings', 'other') NOT NULL DEFAULT 'cash',
    `account_number` VARCHAR(50) DEFAULT NULL,
    `initial_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `color` VARCHAR(20) NOT NULL DEFAULT '#7C3AED',
    `notes` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. TABEL KATEGORI (CATEGORIES)
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL, -- NULL berarti kategori sistem/global bawaan
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `icon` VARCHAR(50) NOT NULL DEFAULT 'tag',
    `color` VARCHAR(20) NOT NULL DEFAULT '#7C3AED',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. TABEL TRANSAKSI (TRANSACTIONS)
CREATE TABLE IF NOT EXISTS `transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `account_id` INT DEFAULT NULL,
    `category_id` INT DEFAULT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `transaction_date` DATE NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `payment_method` VARCHAR(50) DEFAULT 'Tunai',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_date` (`user_id`, `transaction_date`),
    INDEX `idx_user_type` (`user_id`, `type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. TABEL TRANSFER ANTAR-REKENING (TRANSFERS)
CREATE TABLE IF NOT EXISTS `transfers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `from_account_id` INT NOT NULL,
    `to_account_id` INT NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `transfer_date` DATE NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`from_account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`to_account_id`) REFERENCES `accounts`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_transfer` (`user_id`, `transfer_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. TABEL ANGGARAN BULANAN (BUDGETS)
CREATE TABLE IF NOT EXISTS `budgets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `month` TINYINT NOT NULL,
    `year` SMALLINT NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_user_cat_period` (`user_id`, `category_id`, `month`, `year`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. TABEL TARGET TABUNGAN (SAVINGS_GOALS)
CREATE TABLE IF NOT EXISTS `savings_goals` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `target_amount` DECIMAL(15,2) NOT NULL,
    `target_date` DATE DEFAULT NULL,
    `status` ENUM('in_progress', 'completed') NOT NULL DEFAULT 'in_progress',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. TABEL ALOKASI TABUNGAN (SAVINGS_CONTRIBUTIONS)
CREATE TABLE IF NOT EXISTS `savings_contributions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `goal_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `account_id` INT DEFAULT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `contribution_date` DATE NOT NULL,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`goal_id`) REFERENCES `savings_goals`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`account_id`) REFERENCES `accounts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA: KATEGORI DEFAULT SISTEM
-- ========================================================
-- Kategori Pemasukan Sistem
INSERT IGNORE INTO `categories` (`id`, `user_id`, `name`, `type`, `icon`, `color`) VALUES
(1, NULL, 'Gaji', 'income', 'briefcase', '#10B981'),
(2, NULL, 'Uang saku', 'income', 'wallet', '#10B981'),
(3, NULL, 'Bonus', 'income', 'award', '#10B981'),
(4, NULL, 'Usaha', 'income', 'trending-up', '#10B981'),
(5, NULL, 'Freelance', 'income', 'laptop', '#10B981'),
(6, NULL, 'Hadiah', 'income', 'gift', '#10B981'),
(7, NULL, 'Lainnya', 'income', 'folder-plus', '#10B981');

-- Kategori Pengeluaran Sistem
INSERT IGNORE INTO `categories` (`id`, `user_id`, `name`, `type`, `icon`, `color`) VALUES
(8, NULL, 'Makanan dan minuman', 'expense', 'coffee', '#EF4444'),
(9, NULL, 'Transportasi', 'expense', 'truck', '#EF4444'),
(10, NULL, 'Bensin', 'expense', 'fuel', '#EF4444'),
(11, NULL, 'Pendidikan', 'expense', 'book-open', '#EF4444'),
(12, NULL, 'Hiburan', 'expense', 'film', '#EF4444'),
(13, NULL, 'Belanja', 'expense', 'shopping-bag', '#EF4444'),
(14, NULL, 'Tagihan', 'expense', 'receipt', '#EF4444'),
(15, NULL, 'Kesehatan', 'expense', 'activity', '#EF4444'),
(16, NULL, 'Kebutuhan rumah', 'expense', 'home', '#EF4444'),
(17, NULL, 'Langganan aplikasi', 'expense', 'smartphone', '#EF4444'),
(18, NULL, 'Lainnya', 'expense', 'more-horizontal', '#EF4444');

