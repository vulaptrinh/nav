-- ==========================================================
-- CashTrack Database for InfinityFree Hosting
-- Ready for Import in phpMyAdmin (No CREATE DATABASE)
-- ==========================================================

-- ----------------------------------------------------------
-- Structure for table `users`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` enum('admin','user') DEFAULT 'admin',
  `avatar` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `users`
INSERT INTO `users` (`id`, `username`, `email`, `password`, `fullname`, `role`, `avatar`, `remember_token`, `status`, `last_login`, `created_at`) VALUES
('1', 'admin', 'admin@cashtrack.local', '$2y$10$iWHJm8zBVY2i4Ka3kSNvNeUjl0kmZ7qpjFoDwCgC0kBsthL.PRgsO', 'Quản trị viên Hệ thống', 'admin', NULL, '19c3c2cb452eccf686e03afa11a7bb1219521656531f3ede46d5e9e0ec301350', '1', '2026-11-02 10:01:32', '2026-11-02 09:41:42'),
('2', 'demo', 'demo@cashtrack.local', '$2y$10$VnFxlTK2E5hMq2qlT8PuYeDyIJZaapTWtWGhgQL06SA1RSQoHF5CS', 'Người dùng Demo', 'user', NULL, NULL, '1', '2026-11-02 09:47:46', '2026-11-02 09:41:42');

-- ----------------------------------------------------------
-- Structure for table `categories`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense','both') DEFAULT 'both',
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `fk_categories_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `categories`
INSERT INTO `categories` (`id`, `user_id`, `parent_id`, `name`, `type`, `created_at`) VALUES
('1', NULL, NULL, 'Lương & Thưởng', 'income', '2026-11-02 10:05:29'),
('2', NULL, NULL, 'Thu nhập kinh doanh', 'income', '2026-11-02 10:05:29'),
('3', NULL, NULL, 'Bán hàng', 'income', '2026-11-02 10:05:29'),
('4', NULL, NULL, 'Đầu tư cổ tức', 'income', '2026-11-02 10:05:29'),
('5', NULL, NULL, 'Thanh toán khách hàng', 'income', '2026-11-02 10:05:29'),
('6', NULL, NULL, 'Thu nhập khác', 'income', '2026-11-02 10:05:29'),
('7', NULL, NULL, 'Ăn uống & Sinh hoạt', 'expense', '2026-11-02 10:05:29'),
('8', NULL, NULL, 'Tiền thuê phòng / văn phòng', 'expense', '2026-11-02 10:05:29'),
('9', NULL, NULL, 'Chi phí máy chủ & IT', 'expense', '2026-11-02 10:05:29'),
('10', NULL, NULL, 'Đi lại / Xăng xe', 'expense', '2026-11-02 10:05:29'),
('11', NULL, NULL, 'Mua sắm thiết bị', 'expense', '2026-11-02 10:05:29'),
('12', NULL, NULL, 'Hóa đơn điện nước, mạng', 'expense', '2026-11-02 10:05:29'),
('13', NULL, NULL, 'Chi phí giải trí & Cafe', 'expense', '2026-11-02 10:05:29'),
('14', NULL, NULL, 'Khoản chi khác', 'expense', '2026-11-02 10:05:29'),
('15', '1', NULL, 'Học tập & Sách', 'expense', '2026-11-02 10:06:47'),
('16', '1', NULL, 'DÂU', 'expense', '2026-11-02 10:21:03'),
('17', '1', '16', 'Tiền học', 'expense', '2026-11-02 10:32:12'),
('18', '1', NULL, 'Tiền học TTH', 'expense', '2026-11-02 10:40:12'),
('19', '1', '16', 'Tiền học TTH', 'expense', '2026-11-02 11:19:36'),
('20', '1', '16', 'Tiền học Dance Kid', 'expense', '2026-11-02 11:42:40'),
('21', '1', '16', 'Tiền học Tiếng Anh', 'expense', '2026-11-02 11:57:20');

-- ----------------------------------------------------------
-- Structure for table `transactions`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `transactions`;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `transaction_date` (`transaction_date`),
  CONSTRAINT `fk_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- Structure for table `planned_expenses`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `planned_expenses`;
CREATE TABLE `planned_expenses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `subcategory` varchar(100) DEFAULT NULL,
  `expected_amount` decimal(15,2) NOT NULL,
  `repeat_type` enum('daily','weekly','monthly','yearly','custom_days','once') NOT NULL DEFAULT 'monthly',
  `repeat_interval` int(11) DEFAULT 1,
  `day_of_month` tinyint(4) DEFAULT NULL,
  `expected_date` date NOT NULL,
  `actual_date` date DEFAULT NULL,
  `actual_amount` decimal(15,2) DEFAULT NULL,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `note` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expected_date` (`expected_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_planned_expenses_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `planned_expenses`
INSERT INTO `planned_expenses` (`id`, `user_id`, `title`, `category`, `subcategory`, `expected_amount`, `repeat_type`, `repeat_interval`, `day_of_month`, `expected_date`, `actual_date`, `actual_amount`, `status`, `note`, `created_at`) VALUES
('1', '1', 'Lớp Lá 1', 'DÂU', 'Tiền học', '1000000.00', 'monthly', '1', '7', '2026-11-07', NULL, NULL, 'pending', 'Học phí hàng tháng ngày 07', '2026-11-02 10:32:12'),
('2', '1', 'TTH Cô Nhi', 'DÂU', 'Tiền học TTH', '400000.00', 'weekly', '4', NULL, '2026-11-20', NULL, NULL, 'pending', 'T7 CN (17:30 - 19:30)', '2026-11-02 11:27:04'),
('3', '1', 'Dance Kid Cô Ngân', 'DÂU', 'Tiền học Dance Kid', '250000.00', 'monthly', '1', '10', '2026-11-10', NULL, NULL, 'pending', 'Học lớp dance kid T3 - T5 (Thời gian 18:25 - 19:25)', '2026-11-02 11:42:40'),
('4', '1', 'Tiếng Anh Trung Tâm Anh Việt', 'DÂU', 'Tiền học Tiếng Anh', '480000.00', 'monthly', '1', '10', '2026-11-10', NULL, NULL, 'pending', 'Học lớp Tiếng Anh Cô Mai (Thời gian 07:15 - 09:15)', '2026-11-02 11:57:20');

-- ----------------------------------------------------------
-- Structure for table `login_logs`
-- ----------------------------------------------------------
DROP TABLE IF EXISTS `login_logs`;
CREATE TABLE `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `attempted_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Data for table `login_logs`
INSERT INTO `login_logs` (`id`, `username`, `ip_address`, `user_agent`, `status`, `attempted_at`) VALUES
('1', 'admin', '::1', '', 'success', '2026-11-02 09:44:07'),
('2', 'admin', '::1', '', 'success', '2026-11-02 09:44:28'),
('3', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'success', '2026-11-02 09:46:30'),
('4', 'demo', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'success', '2026-11-02 09:47:46'),
('5', 'admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36', 'success', '2026-11-02 10:01:32');

