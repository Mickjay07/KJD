-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Počítač: wh51.farma.gigaserver.cz
-- Vytvořeno: Pon 02. úno 2026, 19:40
-- Verze serveru: 8.0.41
-- Verze PHP: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Databáze: `kubajadesigns_eu_`
--

-- --------------------------------------------------------

--
-- Struktura tabulky `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `email`, `full_name`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$YM/3OrgEzY7nBc/kVDkuqu9V2yu.CTcOKgkZx8aIoJQBWbDo91..e', 'admin@kubajadesigns.eu', 'Administrator', 1, NULL, '2025-09-12 21:15:36', '2025-09-14 21:22:32'),
(2, 'kjd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'info@kubajadesigns.eu', 'KJD Admin', 1, NULL, '2025-09-12 21:15:36', '2025-09-12 21:15:36'),
(4, 'Admin2', '2007MICKEY++', 'mickeyjarolim3@gmail.com', 'Jakub Jarolim', 1, '2025-09-12 23:17:48', '2025-09-12 21:17:34', '2025-09-14 21:21:20');

-- --------------------------------------------------------

--
-- Struktura tabulky `app_settings`
--

CREATE TABLE `app_settings` (
  `id` int NOT NULL,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Vypisuji data pro tabulku `app_settings`
--

INSERT INTO `app_settings` (`id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
(1, 'latest_news_image_url', 'https://kubajadesigns.eu/uploads/app_news/6836c4ca4f7c6.png', '2025-05-28 07:25:48', '2025-05-28 08:09:46');

-- --------------------------------------------------------

--
-- Struktura tabulky `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int NOT NULL,
  `inquiry_id` int NOT NULL,
  `sender_type` enum('customer','admin') NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `color_notifications`
--

CREATE TABLE `color_notifications` (
  `id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_type` enum('product','product2') NOT NULL,
  `color` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `notified` tinyint(1) DEFAULT '0',
  `date_requested` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `color_notifications`
--

INSERT INTO `color_notifications` (`id`, `product_id`, `product_type`, `color`, `email`, `notified`, `date_requested`) VALUES
(2, 33, 'product', '#00eaff', 'mickeyjarolim4@gmail.com', 1, '2025-03-13 11:59:02'),
(3, 33, 'product', '#ffff00', 'mickeyjarolim4@gmail.com', 1, '2025-03-17 10:25:09'),
(6, 6, 'product', '#ffff00', 'mickeyjarolim3@gmail.com', 1, '2025-03-24 21:39:38'),
(7, 33, 'product', '#ffff00', 'mickeyjarolim3@gmail.com', 1, '2025-04-03 20:50:27'),
(8, 100, 'product', 'oranžová', 'mickeyjarolim4@gmail.com', 0, '2025-09-03 20:06:13');

-- --------------------------------------------------------

--
-- Struktura tabulky `complaints`
--

CREATE TABLE `complaints` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text NOT NULL,
  `complaint_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `products` text NOT NULL,
  `photos` text,
  `status` enum('new','in_progress','resolved','rejected') NOT NULL DEFAULT 'new',
  `resolution` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `customer_inquiries`
--

CREATE TABLE `customer_inquiries` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','in_progress','resolved') NOT NULL DEFAULT 'new',
  `admin_reply` text,
  `replied_at` datetime DEFAULT NULL,
  `chat_token` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Struktura tabulky `custom_lightbox_orders`
--

CREATE TABLE `custom_lightbox_orders` (
  `id` int NOT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` enum('small','medium','large') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `has_stand` tinyint(1) NOT NULL DEFAULT '0',
  `base_price` decimal(10,2) NOT NULL,
  `stand_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_price` decimal(10,2) NOT NULL,
  `status` enum('pending_payment','paid','pending_approval','confirmed','changes_requested','in_production','completed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_payment',
  `change_request` text COLLATE utf8mb4_unicode_ci,
  `payment_date` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `final_design_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci,
  `postal_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipping_cost` decimal(10,2) DEFAULT '0.00',
  `wallet_used` tinyint(1) DEFAULT '0',
  `wallet_amount` decimal(10,2) DEFAULT '0.00',
  `amount_to_pay` decimal(10,2) DEFAULT NULL,
  `logo_shape` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'round',
  `aspect_ratio` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '1:1',
  `box_color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'white',
  `quantity` int DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `custom_lightbox_orders`
--

INSERT INTO `custom_lightbox_orders` (`id`, `customer_name`, `customer_email`, `customer_phone`, `image_path`, `size`, `has_stand`, `base_price`, `stand_price`, `total_price`, `status`, `change_request`, `payment_date`, `confirmed_at`, `created_at`, `updated_at`, `final_design_path`, `delivery_method`, `delivery_address`, `postal_code`, `payment_method`, `shipping_cost`, `wallet_used`, `wallet_amount`, `amount_to_pay`, `logo_shape`, `aspect_ratio`, `box_color`, `quantity`) VALUES
(1, 'Jakub Jarolim', 'mickeyjarolim3@gmail.com', '722341256', 'uploads/custom_lightbox/custom_1763503647_691cee1faa60a.png', 'small', 1, 890.00, 200.00, 1090.00, 'paid', NULL, '2025-11-18 23:07:47', NULL, '2025-11-18 23:07:27', '2025-11-18 23:07:47', NULL, NULL, NULL, NULL, NULL, 0.00, 0, 0.00, NULL, 'round', '1:1', 'white', 1),
(2, 'Jakub Jarolim', 'mickeyjarolim3@gmail.com', '722341256', 'uploads/custom_lightbox/custom_1763504708_691cf2441d77a.png', 'medium', 1, 1290.00, 200.00, 1490.00, 'confirmed', NULL, '2025-11-18 23:33:33', '2025-11-18 23:42:17', '2025-11-18 23:25:08', '2025-11-18 23:42:17', 'uploads/custom_lightbox/final/final_1763505702_691cf626d48ec.png', 'Jiná doprava', '', '', 'revolut', 0.00, 0, 0.00, 1490.00, 'round', '1:1', 'white', 1),
(3, 'Jakub Jarolim', 'mickeyjarolim3@gmail.com', '722341256', 'uploads/custom_lightbox/custom_1763579517_691e167db1f7b.png', 'medium', 1, 1290.00, 125.00, 1415.00, 'pending_payment', NULL, NULL, NULL, '2025-11-19 20:11:57', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0, 0.00, NULL, 'square', '1:1', 'black', 1),
(4, 'Jakub Jarolim', 'mickeyjarolim3@gmail.com', '722341256', 'uploads/custom_lightbox/custom_1763579942_691e182611589.png', 'large', 1, 1690.00, 125.00, 1815.00, 'pending_payment', NULL, NULL, NULL, '2025-11-19 20:19:02', NULL, NULL, NULL, NULL, NULL, NULL, 0.00, 0, 0.00, NULL, 'square', '4:3', 'black', 1),
(5, 'Jakub Jarolim', 'mickeyjarolim3@gmail.com', '722341256', 'uploads/custom_lightbox/custom_1763588626_691e3a12d1271.png', 'medium', 1, 1290.00, 125.00, 2830.00, 'paid', NULL, '2025-11-19 22:44:00', NULL, '2025-11-19 22:43:46', '2025-11-19 22:44:00', NULL, 'Jiná doprava', '', '', 'revolut', 0.00, 0, 0.00, 2830.00, 'square', '1:1', 'black', 2);

-- --------------------------------------------------------

--
-- Struktura tabulky `custom_requests`
--

CREATE TABLE `custom_requests` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `material_id` int DEFAULT NULL,
  `infill` varchar(50) DEFAULT NULL,
  `note` text,
  `status` varchar(50) DEFAULT 'pending',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `custom_requests`
--

INSERT INTO `custom_requests` (`id`, `email`, `phone`, `file_path`, `original_filename`, `material_id`, `infill`, `note`, `status`, `created_at`) VALUES
(1, 'mickeyjarolim3@gmail.com', '+420 722 341 256', 'uploads/custom_prints/6921cb0dab7b0_oxvo_xlim_go_case.stl', 'oxvo_xlim_go_case.stl', 1, '10%', '', 'pending', '2025-11-22 15:39:09'),
(2, 'mickeyjarolim3@gmail.com', '+420 722 341 256', 'uploads/custom_prints/6921cd3248c3e_Lilypot.stl', 'Lilypot.stl', 3, '20%', '', 'pending', '2025-11-22 15:48:18'),
(3, 'mickeyjarolim4@gmail.com', '+420 722 341 256', 'uploads/custom_prints/6921ce2907f94_oxvo_xlim_go_case.stl', 'oxvo_xlim_go_case.stl', 1, '10%', '', 'pending', '2025-11-22 15:52:25'),
(4, 'mickeyjarolim4@gmail.com', '+420 722 341 256', 'uploads/custom_prints/6921ce92291a5_oxvo_xlim_go_case.stl', 'oxvo_xlim_go_case.stl', 1, '10%', '', 'pending', '2025-11-22 15:54:10');

-- --------------------------------------------------------

--
-- Struktura tabulky `discount_codes`
--

CREATE TABLE `discount_codes` (
  `id` int NOT NULL,
  `code` varchar(50) NOT NULL,
  `discount_percent` decimal(10,2) NOT NULL COMMENT 'Sleva v procentech (podporuje desetinná čísla)',
  `valid_from` date NOT NULL,
  `valid_to` date NOT NULL,
  `usage_limit` int DEFAULT NULL,
  `times_used` int DEFAULT '0',
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `discount_codes`
--

INSERT INTO `discount_codes` (`id`, `code`, `discount_percent`, `valid_from`, `valid_to`, `usage_limit`, `times_used`, `active`) VALUES
(7, 'SLEVA10', 10.00, '2025-01-08', '2025-05-11', 100000, 1, 1),
(17, 'Vojta', 41.70, '2025-10-27', '2025-12-27', 2, 0, 1),
(18, 'Doprava', 16.40, '2025-11-05', '2026-02-05', 10, 0, 1),
(19, 'NEWSA07A2C', 10.00, '2025-11-06', '2025-12-06', 1, 0, 1),
(20, 'NEWSD1B307', 10.00, '2025-11-06', '2025-12-06', 1, 0, 1),
(21, 'NEWSED4030', 10.00, '2025-11-06', '2025-12-06', 1, 0, 1),
(22, 'NEWS29BE50', 10.00, '2025-11-06', '2025-12-06', 1, 0, 1),
(23, 'NEWS94C059', 10.00, '2025-11-06', '2025-12-06', 1, 0, 1),
(24, 'NEWS7D27C3', 10.00, '2025-11-06', '2025-12-06', 1, 0, 1),
(25, 'NEWSE5014E', 10.00, '2025-11-11', '2025-12-11', 1, 0, 1),
(26, 'NEWS4B30E9', 10.00, '2025-11-23', '2025-12-23', 1, 0, 1),
(27, 'RICHARD', 15.00, '2025-12-02', '2025-12-31', 10, 0, 1),
(28, 'NEWS999DB2', 10.00, '2025-12-05', '2026-01-04', 1, 0, 1),
(29, '99,01', 99.01, '2025-12-08', '2025-12-09', 2, 0, 1),
(30, 'DOPRAVAZDARMA', 0.00, '2025-12-08', '2026-12-08', NULL, 0, 1),
(31, 'NEWS7CBDC5', 10.00, '2025-12-08', '2026-01-07', 1, 0, 1),
(32, 'preorder', 15.00, '2026-01-11', '2026-01-18', 100, 0, 1),
(33, 'NEWSD26CB1', 10.00, '2026-01-14', '2026-02-13', 1, 0, 1);

-- --------------------------------------------------------

--
-- Struktura tabulky `discount_code_products`
--

CREATE TABLE `discount_code_products` (
  `id` int NOT NULL,
  `discount_code_id` int NOT NULL,
  `product_id` int NOT NULL,
  `product_type` varchar(10) DEFAULT 'product'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- --------------------------------------------------------

--
-- Struktura tabulky `email_templates`
--

CREATE TABLE `email_templates` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8mb4_czech_ci NOT NULL,
  `content` text COLLATE utf8mb4_czech_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_czech_ci;

--
-- Vypisuji data pro tabulku `email_templates`
--

INSERT INTO `email_templates` (`id`, `name`, `subject`, `content`, `created_at`) VALUES
(1, 'Reakce na dotaz', 'Re: Váš dotaz', '<p class=\"p1\">Dobr&yacute; den,</p>\r\n<p class=\"p1\">děkuji za V&aacute;&scaron; dotaz.</p>\r\n<p class=\"p1\">N&iacute;že pos&iacute;l&aacute;m informace:</p>\r\n<p class=\"p1\">&bull; &hellip;</p>\r\n<p class=\"p1\">Pokud budete cht&iacute;t, mohu V&aacute;m doporučit i dal&scaron;&iacute; možnosti.</p>\r\n<p class=\"p1\">Hezk&yacute; den,</p>\r\n<p class=\"p2\"><strong>Jakub &ndash; KubaJaDesigns</strong></p>', '2025-11-21 23:50:46'),
(2, 'Spolupráce', ' Možná spolupráce – KubaJaDesigns', '<p class=\"p1\">Dobr&yacute; den,</p>\r\n<p class=\"p1\">r&aacute;d bych se zeptal, zda by V&aacute;s oslovila př&iacute;padn&aacute; spolupr&aacute;ce s moj&iacute; značkou <span class=\"s1\"><strong>KubaJaDesigns</strong></span>, kter&aacute; se specializuje na 3D tisk designov&yacute;ch lamp a doplňků.</p>\r\n<p class=\"p1\">Pokud byste měl/a z&aacute;jem, mohu poslat vzorky produktů nebo domluvit konkr&eacute;tn&iacute; podm&iacute;nky spolupr&aacute;ce.</p>\r\n<p class=\"p1\">Tě&scaron;&iacute;m se na př&iacute;padnou odpověď.</p>\r\n<p class=\"p1\">S pozdravem,</p>\r\n<p class=\"p2\"><strong>Jakub &ndash; KubaJaDesigns</strong></p>', '2025-11-21 23:51:46'),
(3, 'Cenová nabídka – 3D tisk dekorací', 'Cenová nabídka – 3D tisk dekorací', '<p class=\"p1\">Dobr&yacute; den,</p>\r\n<p class=\"p1\">děkuji za z&aacute;jem o firemn&iacute; spolupr&aacute;ci.</p>\r\n<p class=\"p1\">Na z&aacute;kladě Va&scaron;&iacute; popt&aacute;vky pos&iacute;l&aacute;m cen&iacute;k a možnosti v&yacute;roby:</p>\r\n<p class=\"p1\">&bull; &hellip;</p>\r\n<p class=\"p1\">&bull; &hellip;</p>\r\n<p class=\"p1\">Dodac&iacute; lhůta: &hellip;</p>\r\n<p class=\"p1\">Pokud budete m&iacute;t z&aacute;jem, mohu připravit tak&eacute; vzorov&yacute; kus.</p>\r\n<p class=\"p1\">S pozdravem,</p>\r\n<p class=\"p2\"><strong>Jakub &ndash; KubaJaDesigns</strong></p>', '2025-11-21 23:52:42'),
(4, 'Jak jste spokojeni s objednávkou?', 'Jak jste spokojeni s objednávkou?', '<p class=\"p1\">Dobr&yacute; den,</p>\r\n<p class=\"p1\">r&aacute;d bych se zeptal, zda V&aacute;m Va&scaron;e nov&aacute; lampa/květin&aacute;č dorazila v poř&aacute;dku a zda jste s v&yacute;robkem spokojeni.</p>\r\n<p class=\"p1\">Va&scaron;e zpětn&aacute; vazba mi moc pomůže vylep&scaron;ovat dal&scaron;&iacute; produkty.</p>\r\n<p class=\"p1\">Přeji hezk&yacute; den,</p>\r\n<p class=\"p2\"><strong>Jakub &ndash; KubaJaDesigns</strong></p>', '2025-11-21 23:53:15');

-- --------------------------------------------------------

--
-- Struktura tabulky `filaments`
--

CREATE TABLE `filaments` (
  `id` int NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_per_kg` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `filaments`
--

INSERT INTO `filaments` (`id`, `name`, `price_per_kg`, `created_at`) VALUES
(1, 'Alzament PLA Black', 250.00, '2025-11-21 23:14:03'),
(2, 'Alzament PLA White', 250.00, '2025-11-21 23:15:35'),
(3, 'Alzament PLA Red', 250.00, '2025-11-21 23:15:45'),
(4, 'Sunlu Transparent Red', 290.00, '2025-11-22 12:30:39'),
(5, 'Alzament PLA Yellow', 250.00, '2025-11-22 12:31:00'),
(6, 'Elegoo PETG Transparent', 210.00, '2025-11-22 12:32:38'),
(7, 'Elegoo PETG Red', 211.00, '2025-11-22 12:32:59'),
(8, 'Elegoo PETG Black', 238.00, '2025-11-22 12:33:14'),
(9, 'Elegoo PETG Beige', 210.00, '2025-11-22 12:33:25'),
(10, 'Elegoo PETG White', 210.00, '2025-11-22 12:33:31');

-- --------------------------------------------------------

--
-- Struktura tabulky `gopay_payments`
--

CREATE TABLE `gopay_payments` (
  `id` int NOT NULL,
  `order_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gopay_id` bigint NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'CZK',
  `state` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_instrument` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `gopay_payments`
--

INSERT INTO `gopay_payments` (`id`, `order_id`, `gopay_id`, `amount`, `currency`, `state`, `payment_instrument`, `payer_email`, `created_at`, `updated_at`) VALUES
(1, 'KJD-2025-0780', 3287957023, 749.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-05 10:32:11', '2025-12-05 10:32:11'),
(2, 'KJD-2025-3440', 3287964849, 319.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-05 12:08:44', '2025-12-05 12:08:44'),
(3, 'KJD-2025-0909', 3287965213, 749.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-05 12:19:26', '2025-12-05 12:19:26'),
(4, 'KJD-2025-2598', 3287986715, 1447.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-05 19:03:19', '2025-12-05 19:03:19'),
(5, 'KJD-2025-2844', 9201797334, 749.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-08 08:19:41', '2025-12-08 08:19:41'),
(6, 'KJD-2025-0915', 9201808539, 1298.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-08 08:48:28', '2025-12-08 08:48:28'),
(7, 'KJD-2025-7631', 9201811535, 1298.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-08 08:55:45', '2025-12-08 08:55:45'),
(8, 'KJD-2025-5128', 9201815946, 1298.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-08 09:06:41', '2025-12-08 09:06:41'),
(9, 'KJD-2025-5426', 9201835495, 1.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-08 09:54:56', '2025-12-08 09:54:56'),
(10, 'KJD-2025-6112', 9202726619, 664.40, 'CZK', 'CREATED', NULL, NULL, '2025-12-10 07:52:27', '2025-12-10 07:52:27'),
(11, 'KJD-2025-7142', 9205157706, 700.00, 'CZK', 'CREATED', NULL, NULL, '2025-12-15 08:11:18', '2025-12-15 08:11:18');

-- --------------------------------------------------------

--
-- Struktura tabulky `homepage_content`
--

CREATE TABLE `homepage_content` (
  `id` int NOT NULL,
  `headline` varchar(255) NOT NULL,
  `image_url` varchar(512) NOT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `subtitle` varchar(255) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `homepage_content`
--

INSERT INTO `homepage_content` (`id`, `headline`, `image_url`, `updated_at`, `subtitle`) VALUES
(1, 'Nová lampa !', 'https://kubajadesigns.eu/uploads/Bento.png', '2025-07-20 20:17:44', 'Naomi');

-- --------------------------------------------------------

--
-- Struktura tabulky `invoices`
--

CREATE TABLE `invoices` (
  `id` int NOT NULL,
  `order_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_amount` decimal(10,2) DEFAULT '0.00',
  `due_date` date NOT NULL,
  `status` enum('draft','issued','paid','cancelled','sent') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `wallet_used` tinyint(1) DEFAULT '0' COMMENT 'Whether user used wallet balance',
  `wallet_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'Amount deducted from wallet',
  `amount_to_pay` decimal(10,2) DEFAULT '0.00' COMMENT 'Final amount to be paid after wallet deduction',
  `issue_date` date DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'CZK',
  `total_without_vat` decimal(10,2) DEFAULT '0.00',
  `vat_total` decimal(10,2) DEFAULT '0.00',
  `total_with_vat` decimal(10,2) DEFAULT '0.00',
  `buyer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_address1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_address2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_zip` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_ico` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_dic` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Česká republika',
  `buyer_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `buyer_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'bank_transfer',
  `paid_date` timestamp NULL DEFAULT NULL COMMENT 'Date when invoice was marked as paid'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `invoices`
--

INSERT INTO `invoices` (`id`, `order_id`, `invoice_number`, `total_amount`, `due_date`, `status`, `notes`, `created_at`, `updated_at`, `wallet_used`, `wallet_amount`, `amount_to_pay`, `issue_date`, `currency`, `total_without_vat`, `vat_total`, `total_with_vat`, `buyer_name`, `buyer_address1`, `buyer_address2`, `buyer_city`, `buyer_zip`, `buyer_ico`, `buyer_dic`, `buyer_country`, `buyer_email`, `buyer_phone`, `payment_method`, `paid_date`) VALUES
(1, '96', 'KJD-20259001', 639.00, '2025-09-12', 'sent', '', '2025-09-12 21:23:13', '2025-09-13 23:28:57', 0, 0.00, 0.00, NULL, 'CZK', 0.00, 0.00, 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Česká republika', NULL, NULL, 'bank_transfer', NULL),
(4, 'KJD-2025-9037', 'KJD202510001', 0.00, '2025-10-09', 'paid', NULL, '2025-10-09 12:42:57', '2025-11-01 23:23:51', 0, 0.00, 0.00, '2025-10-09', 'CZK', 369.00, 0.00, 369.00, 'Jakub Jarolím', '', '', '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim9@gmail.com', '722341256', 'bank_transfer', '2025-11-01 23:23:51'),
(5, 'KJD-2025-9037', 'KJD202510002', 0.00, '2025-10-20', 'paid', NULL, '2025-10-20 10:25:53', '2025-11-01 23:53:13', 0, 0.00, 0.00, '2025-10-20', 'CZK', 369.00, 0.00, 369.00, 'Jakub Jarolím', '', '', '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim9@gmail.com', '722341256', 'bank_transfer', '2025-11-01 23:53:13'),
(6, 'KJD-2025-9037', 'KJD202510003', 0.00, '2025-10-21', 'paid', NULL, '2025-10-21 08:44:48', '2025-11-02 11:07:21', 0, 0.00, 0.00, '2025-10-21', 'CZK', 369.00, 0.00, 369.00, 'Jakub Jarolím', '', '', '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim9@gmail.com', '722341256', 'bank_transfer', '2025-11-02 11:07:21'),
(7, 'KJD-2025-9037', 'KJD202510004', 0.00, '2025-10-23', 'paid', NULL, '2025-10-23 21:10:50', '2025-11-03 08:19:05', 0, 0.00, 0.00, '2025-10-23', 'CZK', 369.00, 0.00, 369.00, 'Jakub Jarolím', '', '', '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim9@gmail.com', '722341256', 'bank_transfer', '2025-11-03 08:19:05'),
(8, 'KJD-CUSTOM-2025-1860', 'KJD202511001', 0.00, '2025-11-19', 'paid', NULL, '2025-11-19 21:44:59', '2025-11-19 22:15:22', 0, 0.00, 2830.00, '2025-11-19', 'CZK', 5660.00, 0.00, 5660.00, 'Jakub Jarolim', 'Mezilesi 2078', '', 'Praha', '19300', NULL, NULL, 'Česká republika', 'mickeyjarolim3@gmail.com', '722341256', 'revolut', '2025-11-19 22:15:22'),
(9, 'KJD-2025-0298', 'KJD202511002', 0.00, '2025-11-19', 'paid', NULL, '2025-11-19 22:00:01', '2025-11-19 22:00:12', 0, 0.00, 0.00, '2025-11-19', 'CZK', 639.00, 0.00, 639.00, 'Jakub Jarolim', '', '', '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim9@gmail.com', '7223412567', 'bank_transfer', '2025-11-19 22:00:12'),
(10, 'KJD-2025-8384', '20250001', 0.00, '2025-12-04', 'paid', NULL, '2025-11-20 22:22:36', '2025-11-20 22:22:40', 0, 0.00, 0.00, '2025-11-20', 'CZK', 499.00, 0.00, 499.00, 'Jakub Jarolim', 'Mezilesi 2078, Praha, 19300', '', '', '', '', '', 'Česká republika', 'mickeyjarolim4@gmail.com', '724987234', 'bank_transfer', '2025-11-20 22:22:40'),
(11, 'KJD-2025-6390', '20250002', 0.00, '2025-12-05', 'paid', NULL, '2025-11-21 12:44:52', '2025-11-21 12:46:36', 0, 0.00, 0.00, '2025-11-21', 'CZK', 319.00, 0.00, 319.00, 'Jan Balous', 'Hartigova 2660/141', '', 'Praha 3', '130 00', '', '', 'Česká republika', 'mickeyjarolim3@gmail.com', '733485310', 'bank_transfer', '2025-11-21 12:46:36'),
(12, 'KJD-2025-6272', 'KJD202512001', 0.00, '2025-12-18', 'paid', NULL, '2025-12-04 13:58:14', '2025-12-04 13:59:04', 0, 0.00, 0.00, '2025-12-04', 'CZK', 0.00, 0.00, 0.00, 'Jakub Jarolim', 'Z-BOX Praha 9, Vysočany, Českomoravská 25, Českomoravská 25 , 190 00 Praha', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim4@gmail.com', '', 'bank_transfer', '2025-12-04 13:59:04'),
(13, 'TEST-GOPAY-20251205125233', 'KJD202512002', 0.00, '2025-12-19', 'paid', NULL, '2025-12-05 11:52:33', '2025-12-05 11:52:33', 0, 0.00, 0.00, '2025-12-05', 'CZK', 0.00, 0.00, 0.00, 'Test User', 'Test Address', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim3@gmail.com', '', 'gopay', '2025-12-05 11:52:33'),
(15, 'TEST-GOPAY-20251205125530', 'KJD202512004', 0.00, '2025-12-19', 'paid', NULL, '2025-12-05 11:55:30', '2025-12-05 11:55:30', 0, 0.00, 605.00, '2025-12-05', 'CZK', 500.00, 105.00, 605.00, 'Test User', 'Test Address', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim3@gmail.com', '', 'gopay', '2025-12-05 11:55:30'),
(18, 'KJD-2025-3440', 'KJD202512007', 0.00, '2025-12-19', 'paid', NULL, '2025-12-05 12:09:22', '2025-12-05 12:09:22', 0, 0.00, 219.00, '2025-12-05', 'CZK', 219.00, 0.00, 219.00, 'Jakub Jarolim', 'Praha 20, Horní Počernice, Náchodská 868/28 (tiskárna Printea), Náchodská 868/28 , 193 00 Praha', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim3@gmail.com', '', 'gopay', '2025-12-05 12:09:22'),
(19, 'KJD-2025-0909', 'KJD202512008', 0.00, '2025-12-19', 'paid', NULL, '2025-12-05 12:20:07', '2025-12-05 12:20:07', 0, 0.00, 749.00, '2025-12-05', 'CZK', 749.00, 0.00, 749.00, 'Jakub Jarolim', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim4@gmail.com', '', 'gopay', '2025-12-05 12:20:07'),
(20, 'KJD-2025-2598', 'KJD202512009', 0.00, '2025-12-19', 'paid', NULL, '2025-12-05 19:04:52', '2025-12-05 19:04:52', 0, 0.00, 1447.00, '2025-12-05', 'CZK', 1447.00, 0.00, 1447.00, 'Jakub Jarolim', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim3@gmail.com', '', 'gopay', '2025-12-05 19:04:52'),
(21, 'KJD-2025-5426', 'KJD202512010', 0.00, '2025-12-22', 'paid', NULL, '2025-12-08 09:56:05', '2025-12-08 09:56:05', 0, 0.00, 1.00, '2025-12-08', 'CZK', 1.00, 0.00, 1.00, 'Jakub Jarolim', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', NULL, '', '', NULL, NULL, 'Česká republika', 'mickeyjarolim4@gmail.com', '', 'gopay', '2025-12-08 09:56:05'),
(22, 'KJD-2025-6112', 'KJD202512011', 0.00, '2025-12-24', 'paid', NULL, '2025-12-10 07:54:05', '2025-12-17 09:24:46', 0, 0.00, 764.00, '2025-12-10', 'CZK', 764.00, 0.00, 664.00, 'Richard Štangl', 'Z-BOX Praha 8, Libeň, Na Dědince, Na Dědince , 180 00 Praha', '', '', '', NULL, NULL, 'Česká republika', 'risa.stangl@gmail.com', '', 'card', '2025-12-17 09:24:46'),
(23, 'KJD-2025-7142', 'KJD202512012', 0.00, '2025-12-29', 'paid', NULL, '2025-12-15 08:16:34', '2025-12-15 08:16:34', 0, 0.00, 700.00, '2025-12-15', 'CZK', 700.00, 0.00, 700.00, 'Magdalena Pruso', 'Z-BOX Praha 8, Karlín, Rohanské nábřeží 672/13, Rohanské nábřeží 672/13 , 180 00 Praha', NULL, '', '', NULL, NULL, 'Česká republika', 'pruso.magdalena@gmail.com', '', 'gopay', '2025-12-15 08:16:34');

-- --------------------------------------------------------

--
-- Struktura tabulky `invoice_counters`
--

CREATE TABLE `invoice_counters` (
  `period` varchar(6) NOT NULL,
  `last_number` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `invoice_counters`
--

INSERT INTO `invoice_counters` (`period`, `last_number`, `created_at`, `updated_at`) VALUES
('202510', 4, '2025-10-09 12:42:57', '2025-10-23 21:10:50'),
('202511', 2, '2025-11-19 21:44:59', '2025-11-19 22:00:01'),
('202512', 12, '2025-12-04 13:58:14', '2025-12-15 08:16:34');

-- --------------------------------------------------------

--
-- Struktura tabulky `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price_without_vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat_rate` decimal(5,2) DEFAULT '0.00',
  `total_without_vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_with_vat` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `invoice_items`
--

INSERT INTO `invoice_items` (`id`, `invoice_id`, `name`, `quantity`, `unit_price_without_vat`, `vat_rate`, `total_without_vat`, `vat_amount`, `total_with_vat`, `created_at`) VALUES
(1, 4, 'Sada z jarní edice květináčů', 1, 279.00, 0.00, 279.00, 0.00, 279.00, '2025-10-09 12:42:57'),
(2, 5, 'Sada z jarní edice květináčů', 1, 279.00, 0.00, 279.00, 0.00, 279.00, '2025-10-20 10:25:53'),
(3, 6, 'Sada z jarní edice květináčů', 1, 279.00, 0.00, 279.00, 0.00, 279.00, '2025-10-21 08:44:48'),
(4, 7, 'Sada z jarní edice květináčů', 1, 279.00, 0.00, 279.00, 0.00, 279.00, '2025-10-23 21:10:50'),
(8, 8, 'Custom Lightbox - Střední × 2', 2, 2830.00, 0.00, 0.00, 0.00, 5660.00, '2025-11-19 21:49:58'),
(9, 9, 'Lampa- Naomi', 1, 549.00, 0.00, 549.00, 0.00, 549.00, '2025-11-19 22:00:01'),
(10, 10, 'Spiral Waeva', 1, 399.00, 0.00, 0.00, 0.00, 399.00, '2025-11-20 22:22:36'),
(11, 10, 'Doprava', 1, 100.00, 0.00, 0.00, 0.00, 100.00, '2025-11-20 22:22:36'),
(14, 11, 'Spiral Waeva', 1, 219.00, 0.00, 0.00, 0.00, 219.00, '2025-11-21 12:46:31'),
(15, 11, 'Doprava', 1, 100.00, 0.00, 0.00, 0.00, 100.00, '2025-11-21 12:46:31'),
(17, 15, 'Test Product', 1, 500.00, 21.00, 500.00, 105.00, 605.00, '2025-12-05 11:55:30'),
(20, 18, 'Spiral Waeva', 1, 219.00, 0.00, 219.00, 0.00, 219.00, '2025-12-05 12:09:22'),
(21, 19, 'Lampa- WAVEA', 1, 649.00, 0.00, 649.00, 0.00, 649.00, '2025-12-05 12:20:07'),
(22, 19, 'Doprava', 1, 100.00, 0.00, 100.00, 0.00, 100.00, '2025-12-05 12:20:07'),
(23, 20, 'Lampa- WAVEA', 1, 649.00, 0.00, 649.00, 0.00, 649.00, '2025-12-05 19:04:52'),
(24, 20, 'Spiral Waeva', 2, 399.00, 0.00, 798.00, 0.00, 798.00, '2025-12-05 19:04:52'),
(25, 21, 'TEST', 1, 1.00, 0.00, 1.00, 0.00, 1.00, '2025-12-08 09:56:05'),
(29, 23, 'Lampa- Spirála světla', 1, 600.00, 0.00, 600.00, 0.00, 600.00, '2025-12-15 08:16:34'),
(30, 23, 'Doprava', 1, 100.00, 0.00, 100.00, 0.00, 100.00, '2025-12-15 08:16:34'),
(31, 22, 'Květináč- Verde Spiral', 1, 64.00, 0.00, 0.00, 0.00, 64.00, '2025-12-17 08:22:19'),
(32, 22, 'Lampa- Aurora', 1, 600.00, 0.00, 0.00, 0.00, 600.00, '2025-12-17 08:22:19'),
(33, 22, 'Doprava', 1, 100.00, 0.00, 0.00, 0.00, 100.00, '2025-12-17 08:22:19');

-- --------------------------------------------------------

--
-- Struktura tabulky `invoice_settings`
--

CREATE TABLE `invoice_settings` (
  `id` int NOT NULL,
  `seller_name` varchar(255) NOT NULL,
  `seller_address1` varchar(255) DEFAULT NULL,
  `seller_address2` varchar(255) DEFAULT NULL,
  `seller_city` varchar(120) DEFAULT NULL,
  `seller_zip` varchar(30) DEFAULT NULL,
  `seller_country` varchar(120) DEFAULT NULL,
  `seller_ico` varchar(60) DEFAULT NULL,
  `seller_dic` varchar(60) DEFAULT NULL,
  `bank_account` varchar(120) DEFAULT NULL,
  `bank_iban` varchar(64) DEFAULT NULL,
  `bank_bic` varchar(32) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `default_due_days` int NOT NULL DEFAULT '14',
  `invoice_prefix` varchar(32) NOT NULL DEFAULT 'KJD',
  `numbering_format` varchar(64) NOT NULL DEFAULT 'KJDYYYYMMNNN',
  `email_from_name` varchar(255) DEFAULT NULL,
  `email_from_address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Vypisuji data pro tabulku `invoice_settings`
--

INSERT INTO `invoice_settings` (`id`, `seller_name`, `seller_address1`, `seller_address2`, `seller_city`, `seller_zip`, `seller_country`, `seller_ico`, `seller_dic`, `bank_account`, `bank_iban`, `bank_bic`, `bank_name`, `default_due_days`, `invoice_prefix`, `numbering_format`, `email_from_name`, `email_from_address`, `created_at`, `updated_at`) VALUES
(1, 'KJD', 'Mezilesí 2078', NULL, 'Praha 20', '19300', 'Česká republika', NULL, NULL, '2686886019/3030', NULL, NULL, NULL, 14, 'KJD', 'KJDYYYYMMNNN', NULL, NULL, '2025-08-25 11:25:02', NULL);

-- --------------------------------------------------------

--
-- Struktura tabulky `lamps`
--

CREATE TABLE `lamps` (
  `id` int NOT NULL,
  `serial_number` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `material` varchar(100) DEFAULT NULL,
  `date_produced` varchar(10) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `lamp_type` varchar(50) DEFAULT NULL,
  `manual_image_url` varchar(255) DEFAULT NULL,
  `manual_text` text,
  `product_id` int DEFAULT NULL,
  `order_id` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `lamps`
--

INSERT INTO `lamps` (`id`, `serial_number`, `name`, `description`, `material`, `date_produced`, `image_url`, `created_at`, `lamp_type`, `manual_image_url`, `manual_text`, `product_id`, `order_id`) VALUES
(1, '1', 'Lampa-Vlnka světla', 'Naše úplně první lampa z řady Vlnka světla', 'PETG, PLA', '5.1.2025', 'https://kubajadesigns.eu/uploads/IMG_9218_jpg.jpeg', '2025-01-27 18:00:20', 'Vlnka Světla', 'https://kubajadesigns.eu/uploads/vlnka navod.jpeg', 'Navod test', NULL, NULL),
(2, '7649', 'Spirála světla', 'Naše úplně první lampa z řady Spirála světla.', 'PETG, PLA', '25.1.2025', 'https://kubajadesigns.eu/uploads/spirala.jpeg', '2025-01-29 10:34:44', NULL, NULL, NULL, NULL, NULL),
(3, '435', 'Spirála světla', 'První prodaná lampa z naší série Spirála světla.', 'PETG, PLA ', '24.1.2025', 'https://www.dropbox.com/scl/fi/wuytc5lprtgdprtkzdk2m/435.jpg?rlkey=udebmc1ms8v4lss4dyzmuql3w&st=ppwjzwf3&raw=1', '2025-02-01 13:05:32', NULL, NULL, NULL, NULL, NULL),
(4, '489', 'Aurora', 'Děkuji ti Deniso za zakoupení nových lamp!', 'PLA+, PETG', '23.2.2025', '', '2025-02-27 22:23:05', NULL, NULL, NULL, NULL, NULL),
(5, '984', 'Aurora', 'Děkuji ti Deniso za zakoupení nových lamp!', 'PLA+, PETG', '23.2.2025', '', '2025-02-27 22:23:38', NULL, NULL, NULL, NULL, NULL),
(6, '398', 'Lampa- Naomi', 'První zakoupená lampa řady Naomi ! Děkujeme !', 'PLA', '01.06.2025', 'https://kubajadesigns.eu/uploads/products/683591f9565cc_IMG_3589.jpeg', '2025-06-12 19:47:48', NULL, NULL, NULL, NULL, NULL),
(8, 'KJD-250821-05CDF3', 'Lampa- Shroom', NULL, 'PLA, Polykarbonát', '2025-08-21', NULL, '2025-08-21 18:02:02', NULL, NULL, NULL, 100, '2025082041059'),
(9, 'KJD-250821-0CBFA1', 'Lampa- Vlnka světla', NULL, 'PLA, Polykarbonát', '2025-08-21', NULL, '2025-08-21 18:02:02', NULL, NULL, NULL, 22, '2025082041059'),
(10, 'KJD-250904-580CA7', 'Lampa- Shroom', NULL, 'PLA, Polykarbonát', '2025-09-04', NULL, '2025-09-04 10:21:44', NULL, NULL, NULL, 100, '2025090304574'),
(11, 'KJD-251014-D6D8EA', 'Lampa- Shroom', NULL, 'PLA', '2025-10-01', NULL, '2025-10-14 06:42:01', NULL, NULL, NULL, 100, 'KJD-2025-9037'),
(12, 'KJD-251014-E4EC89', 'Lampa- Spirála světla', NULL, 'PLA, Polykarbonát', '2025-10-01', NULL, '2025-10-14 06:42:01', NULL, NULL, NULL, 24, 'KJD-2025-9037');

-- --------------------------------------------------------

--
-- Struktura tabulky `lamp_ce_files`
--

CREATE TABLE `lamp_ce_files` (
  `lamp_id` int NOT NULL,
  `ce_file_path` varchar(512) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Vypisuji data pro tabulku `lamp_ce_files`
--

INSERT INTO `lamp_ce_files` (`lamp_id`, `ce_file_path`) VALUES
(8, '/CE/SHROOM/Navod-k-pouziti.pdf'),
(8, '/CE/SHROOM/Prohlášení shodě- SHROOM.pdf'),
(9, '/CE/SHROOM/Navod-k-pouziti.pdf'),
(10, '/CE/SHROOM/Navod-k-pouziti.pdf'),
(10, '/CE/SHROOM/Prohlášení shodě- SHROOM.pdf');

-- --------------------------------------------------------

--
-- Struktura tabulky `lamp_components`
--

CREATE TABLE `lamp_components` (
  `id` int NOT NULL,
  `type` enum('base','shade') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_modifier` decimal(10,2) DEFAULT '0.00',
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `lamp_components`
--

INSERT INTO `lamp_components` (`id`, `type`, `name`, `price_modifier`, `active`, `created_at`) VALUES
(8, 'base', '1', 0.00, 1, '2025-11-25 10:13:24'),
(9, 'base', '2', 0.00, 1, '2025-11-25 10:13:26'),
(10, 'base', '3', 0.00, 1, '2025-11-25 10:13:28'),
(11, 'base', '4', 0.00, 1, '2025-11-25 10:13:29'),
(12, 'base', '5', 0.00, 1, '2025-11-25 10:13:31'),
(13, 'base', '6', 0.00, 1, '2025-11-25 10:13:32'),
(14, 'base', '7', 0.00, 1, '2025-11-25 10:13:34'),
(15, 'base', '8', 0.00, 1, '2025-11-25 10:13:35'),
(16, 'base', '9', 0.00, 1, '2025-11-25 10:13:37'),
(17, 'base', '10', 0.00, 1, '2025-11-25 10:13:39'),
(18, 'base', '11', 0.00, 1, '2025-11-25 10:13:40'),
(19, 'base', '12', 0.00, 1, '2025-11-25 10:13:42'),
(20, 'base', '13', 0.00, 1, '2025-11-25 10:13:44'),
(21, 'base', '14', 0.00, 1, '2025-11-25 10:13:46'),
(22, 'base', '15', 0.00, 1, '2025-11-25 10:13:48'),
(23, 'base', '16', 0.00, 1, '2025-11-25 10:13:50'),
(24, 'base', '17', 0.00, 1, '2025-11-25 10:13:53'),
(25, 'base', '18', 0.00, 1, '2025-11-25 10:13:55'),
(26, 'base', '19', 0.00, 1, '2025-11-25 10:13:57'),
(27, 'base', '20', 0.00, 1, '2025-11-25 10:13:59');

-- --------------------------------------------------------

--
-- Struktura tabulky `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int NOT NULL,
  `email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `newsletter`
--

INSERT INTO `newsletter` (`id`, `email`, `created_at`) VALUES
(4, 'klara@simice.cz', '2025-01-14 19:59:56'),
(6, 'tatjana.pechnikova@seznam.cz', '2025-01-29 08:22:20'),
(9, 'yanushevichmasha00@gmail.com', '2025-03-05 15:26:14'),
(21, 'ofjakub07@gmail.com', '2025-11-06 08:15:57'),
(22, 'mickeyjarolim11@gmail.com', '2025-11-06 09:54:47'),
(23, 'mickeyjarolim3@gmail.com', '2025-11-06 10:12:54'),
(26, 'mickeyjarolim12@gmail.com', '2025-11-06 11:25:09'),
(27, 'honza.broz2008@seznam.cz', '2025-11-11 11:48:25'),
(28, '1999249@seznam.cz', '2025-11-23 06:39:56'),
(29, 'brichacek.vojtech@seznam.cz', '2025-12-05 15:35:22'),
(30, 'ondra.origami@seznam.cz', '2025-12-08 20:44:49'),
(31, 'subrtovaalenkaa@seznam.cz', '2026-01-14 05:31:25');

-- --------------------------------------------------------

--
-- Struktura tabulky `newsletter_history`
--

CREATE TABLE `newsletter_history` (
  `id` int NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `recipient_count` int DEFAULT '0',
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `newsletter_history`
--

INSERT INTO `newsletter_history` (`id`, `subject`, `content`, `recipient_count`, `sent_at`) VALUES
(1, 'Test newsletter', 'Obsah', 1, '2025-11-05 10:18:23'),
(2, 'Test newsletter', 'mhhjs', 1, '2025-11-05 18:26:33'),
(3, 'Test', 'wdawd', 1, '2025-11-05 18:54:24'),
(4, 'Test', 'wdawd', 1, '2025-11-05 19:01:21'),
(5, 'Test', 'dw', 1, '2025-11-05 19:01:30'),
(6, 'Miluju tě', 'FORTNITEEEE', 1, '2025-11-05 19:03:18'),
(7, 'Miluju tě', 'FORTNITEEEE', 1, '2025-11-05 19:06:32'),
(8, 'Test newsletter', 'mhhjs', 1, '2025-11-06 08:14:46'),
(9, 'Test newsletter', 'mhhjs', 1, '2025-11-06 12:03:49');

-- --------------------------------------------------------

--
-- Struktura tabulky `newsletter_subscribers`
--

CREATE TABLE `newsletter_subscribers` (
  `id` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `discount_code` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `newsletter_subscribers`
--

INSERT INTO `newsletter_subscribers` (`id`, `email`, `discount_code`, `created_at`) VALUES
(7, 'test@example.com', 'NEWS6B4542', '2025-09-10 11:59:23'),
(8, 'mickeyjarolim9@gmail.com', 'NEWSE274AC', '2025-09-10 12:01:04'),
(9, 'mickeyjarolim4@gmail.com', 'NEWS4B79A3', '2025-09-10 17:59:30');

-- --------------------------------------------------------

--
-- Struktura tabulky `novinky_aplikace`
--

CREATE TABLE `novinky_aplikace` (
  `id` int NOT NULL,
  `titulek` varchar(255) NOT NULL,
  `obsah` text NOT NULL,
  `obrazek` varchar(255) DEFAULT NULL,
  `datum_vytvoreni` datetime DEFAULT CURRENT_TIMESTAMP,
  `aktivni` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `novinky_aplikace`
--

INSERT INTO `novinky_aplikace` (`id`, `titulek`, `obsah`, `obrazek`, `datum_vytvoreni`, `aktivni`) VALUES
(1, 'Vítejte v nové aplikaci!', 'Spustili jsme novou verzi aplikace KubaJa Designs. Sledujte novinky a aktualizace!', NULL, '2025-07-13 01:17:20', 1),
(2, 'Letní kolekce je tady', 'Představujeme naši novou letní kolekci lamp a dekorací. Prohlédněte si ji v katalogu!', 'uploads/novinky/leto2024.jpg', '2025-07-13 01:17:20', 1),
(3, 'Doprava zdarma', 'Při nákupu nad 2000 Kč nyní získáváte dopravu zdarma po celé ČR.', NULL, '2025-07-13 01:17:20', 1),
(4, 'Plánovaná údržba', 'V noci z 15. na 16. června bude probíhat plánovaná údržba serveru. Děkujeme za pochopení.', NULL, '2025-07-13 01:17:20', 1);

-- --------------------------------------------------------

--
-- Struktura tabulky `orders`
--

CREATE TABLE `orders` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `order_id` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `delivery_method` varchar(255) DEFAULT NULL,
  `packeta_branch_id` varchar(50) DEFAULT NULL,
  `zasilkovna_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `total_price` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) DEFAULT 'Přijato',
  `packeta_packet_id` varchar(50) DEFAULT NULL,
  `packeta_barcode` varchar(50) DEFAULT NULL,
  `packeta_tracking_url` varchar(255) DEFAULT NULL,
  `packeta_label_printed` tinyint(1) DEFAULT '0',
  `tracking_code` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `note` text,
  `products_json` text,
  `is_preorder` tinyint(1) NOT NULL DEFAULT '0',
  `release_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT 'bank_transfer',
  `gopay_payment_id` bigint DEFAULT NULL,
  `payment_status` varchar(20) DEFAULT 'pending',
  `shipping_cost` decimal(10,2) DEFAULT '0.00',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_info` text,
  `variants` json DEFAULT NULL,
  `revolut_payment_link` text,
  `invoice_file` varchar(255) DEFAULT NULL,
  `invoice_sent_at` datetime DEFAULT NULL,
  `payment_confirmed_at` datetime DEFAULT NULL,
  `wallet_used` tinyint(1) DEFAULT '0' COMMENT 'Whether user used wallet balance',
  `wallet_amount` decimal(10,2) DEFAULT '0.00' COMMENT 'Amount deducted from wallet',
  `amount_to_pay` decimal(10,2) DEFAULT '0.00' COMMENT 'Final amount to be paid after wallet deduction',
  `is_custom_lightbox` tinyint(1) DEFAULT '0',
  `custom_lightbox_order_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_id`, `email`, `phone_number`, `name`, `delivery_method`, `packeta_branch_id`, `zasilkovna_name`, `address`, `postal_code`, `total_price`, `status`, `packeta_packet_id`, `packeta_barcode`, `packeta_tracking_url`, `packeta_label_printed`, `tracking_code`, `created_at`, `note`, `products_json`, `is_preorder`, `release_date`, `payment_method`, `gopay_payment_id`, `payment_status`, `shipping_cost`, `transaction_id`, `payment_info`, `variants`, `revolut_payment_link`, `invoice_file`, `invoice_sent_at`, `payment_confirmed_at`, `wallet_used`, `wallet_amount`, `amount_to_pay`, `is_custom_lightbox`, `custom_lightbox_order_id`) VALUES
(120, NULL, 'KJD-2025-6390', 'balous.jann@gmail.com', '733485310', 'Jan Balous', 'AlzaBox', NULL, '', 'Hartigova 2660/141', '', 319.00, 'shipped', NULL, NULL, NULL, 0, 'TRK-F7B4562A', '2025-11-21 12:23:16', '[AlzaBox] Hartigova 2660/141', '{\"105-product-Červená-a61f62e1c480a322b17daf4e33e88b01-\":{\"id\":105,\"product_type\":\"product\",\"name\":\"Spiral Waeva\",\"price\":219,\"final_price\":219,\"selected_color\":\"Červená\",\"color_price\":0,\"quantity\":1,\"image_url\":\"uploads\\/products\\/691f105a6a079_KJD.png\",\"variants\":{\"Počet kusů\":\"3ks\"},\"variant_price_adjustment\":140,\"component_colors\":[]},\"_delivery_info\":{\"alzabox\":{\"code\":\"Hartigova 2660\\/141\"}}}', 0, NULL, 'bank_transfer', NULL, 'paid', 100.00, NULL, NULL, NULL, '', NULL, NULL, NULL, 0, 0.00, 319.00, 0, NULL),
(121, NULL, 'KJD-2025-8652', 'mickeyjarolim4@gmail.com', '678876667', 'Jakub Jarolim', 'Zásilkovna', NULL, 'Z-BOX Praha 9, Vysočany, Českomoravská 25', 'Z-BOX Praha 9, Vysočany, Českomoravská 25, Českomoravská 25 , 190 00 Praha', '190 00', 749.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-5FF5DEE1', '2025-12-01 08:59:58', '', '{\"100-product-Oranžová--\":{\"id\":100,\"product_type\":\"product\",\"name\":\"Lampa- Shroom\",\"price\":649,\"final_price\":649,\"selected_color\":\"Oranžová\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1E8JlrAizaRqho2E0tMRBuyhu55J1YQqL\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]}}', 0, NULL, 'bank_transfer', NULL, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 749.00, 0, NULL),
(122, NULL, 'KJD-2025-6272', 'mickeyjarolim4@gmail.com', '777777777', 'Jakub Jarolim', 'Zásilkovna', '34245', 'Z-BOX Praha 9, Vysočany, Českomoravská 25', 'Z-BOX Praha 9, Vysočany, Českomoravská 25, Českomoravská 25 , 190 00 Praha', '190 00', 179.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-33C688C4', '2025-12-01 11:10:51', '', '{\"105-product-Zelená-392cdab8863da5cbb1b4eeac15828406-\":{\"id\":105,\"product_type\":\"product\",\"name\":\"Spiral Waeva\",\"price\":79,\"final_price\":79,\"selected_color\":\"Zelená\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/drive.google.com\\/file\\/d\\/1PYZI3vw4Fsk6nWV7A_dww40Ohpf_rLzu\\/view?usp=sharing\",\"variants\":{\"Počet kusů\":\"1ks\"},\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]}}', 0, NULL, 'bank_transfer', NULL, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 179.00, 0, NULL),
(123, NULL, 'KJD-2025-3001', 'mickeyjarolim4@gmail.com', '678765678', 'Jakub Jarolim', 'Zásilkovna', '34245', 'Z-BOX Praha 9, Vysočany, Českomoravská 25', 'Z-BOX Praha 9, Vysočany, Českomoravská 25, Českomoravská 25 , 190 00 Praha', '190 00', 749.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-8998EBEA', '2025-12-05 10:11:06', '', '{\"102-product---095a74a532eaeab856548016dcd76a05\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Nožičky\":\"Červená\",\"Horní část\":\"Červená\",\"Vršek\":\"Bílá\",\"Spodní část\":\"Bílá\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', NULL, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 749.00, 0, NULL),
(124, NULL, 'KJD-2025-1640', 'mickeyjarolim4@gmail.com', '678765678', 'Jakub Jarolim', 'Zásilkovna', '29592', 'Z-BOX Praha 9, Vysočany, Na Harfě 929/12', 'Z-BOX Praha 9, Vysočany, Na Harfě 929/12, Na Harfě 929/12 , 190 00 Praha', '190 00', 749.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-2A049689', '2025-12-05 10:14:00', '', '{\"102-product---d2e9b4e86442749adda181b11646a655\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Zelená\",\"Spodní část\":\"Zelená\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', NULL, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 749.00, 0, NULL),
(125, NULL, 'KJD-2025-0780', 'mickeyjarolim4@gmail.com', '768867687', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', '190 00', 749.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-6A6A9E72', '2025-12-05 10:32:10', '', '{\"102-product---d2e9b4e86442749adda181b11646a655\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Zelená\",\"Spodní část\":\"Zelená\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', 3287957023, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 749.00, 0, NULL),
(126, NULL, 'TEST-GOPAY-20251205125233', 'mickeyjarolim3@gmail.com', '+420123456789', 'Test User', 'Zásilkovna', NULL, NULL, 'Test Address', NULL, 500.00, 'pending', NULL, NULL, NULL, 0, NULL, '2025-12-05 11:52:33', NULL, '[{\"name\":\"Test Product\",\"price\":500,\"final_price\":500,\"quantity\":1}]', 0, NULL, 'gopay', 3287963536, 'pending', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 500.00, 0, NULL),
(128, NULL, 'TEST-GOPAY-20251205125530', 'mickeyjarolim3@gmail.com', '+420123456789', 'Test User', 'Zásilkovna', NULL, NULL, 'Test Address', NULL, 500.00, 'pending', NULL, NULL, NULL, 0, NULL, '2025-12-05 11:55:30', NULL, '[{\"name\":\"Test Product\",\"price\":500,\"final_price\":500,\"quantity\":1}]', 0, NULL, 'gopay', 3287963705, 'pending', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 500.00, 0, NULL),
(131, NULL, 'KJD-2025-3440', 'mickeyjarolim3@gmail.com', '722341256', 'Jakub Jarolim', 'Zásilkovna', '6276', 'Praha 20, Horní Počernice, Náchodská 868/28 (tiskárna Printea)', 'Praha 20, Horní Počernice, Náchodská 868/28 (tiskárna Printea), Náchodská 868/28 , 193 00 Praha', '193 00', 319.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-F75493E3', '2025-12-05 12:08:43', '', '{\"105-product-Oranžová-a61f62e1c480a322b17daf4e33e88b01-\":{\"id\":105,\"product_type\":\"product\",\"name\":\"Spiral Waeva\",\"price\":219,\"final_price\":219,\"selected_color\":\"Oranžová\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1PYZI3vw4Fsk6nWV7A_dww40Ohpf_rLzu\",\"variants\":{\"Počet kusů\":\"3ks\"},\"variant_price_adjustment\":140,\"component_colors\":[],\"catalog_selection\":[]}}', 0, NULL, 'gopay', 3287964849, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 319.00, 0, NULL),
(132, NULL, 'KJD-2025-0909', 'mickeyjarolim4@gmail.com', '678765678', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', '190 00', 749.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-A706BC47', '2025-12-05 12:19:26', '', '{\"102-product---d2e9b4e86442749adda181b11646a655\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Zelená\",\"Spodní část\":\"Zelená\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', 3287965213, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 749.00, 0, NULL),
(133, NULL, 'KJD-2025-2598', 'mickeyjarolim3@gmail.com', '687768867', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Vysočany, Čerpadlová (Rezidence Harfistka)', '190 00', 1447.00, 'processing', NULL, NULL, NULL, 0, 'TRK-6882163A', '2025-12-05 19:03:18', '[Zásilkovna] Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Vysočany, Čerpadlová (Rezidence Harfistka)', '{\"102-product---d2e9b4e86442749adda181b11646a655\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Zelená\",\"Spodní část\":\"Zelená\"},\"catalog_selection\":[]},\"105-product-Červená-b83d6acb4b5ab345ea5499f2a449f7c3-\":{\"id\":105,\"product_type\":\"product\",\"name\":\"Spiral Waeva\",\"price\":399,\"final_price\":399,\"selected_color\":\"Červená\",\"color_price\":0,\"quantity\":2,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1PYZI3vw4Fsk6nWV7A_dww40Ohpf_rLzu\",\"variants\":{\"Počet kusů\":\"6ks\"},\"variant_price_adjustment\":320,\"component_colors\":[],\"catalog_selection\":[]},\"_delivery_info\":{\"zasilkovna\":{\"name\":\"Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)\",\"street\":\"Vysočany\",\"city\":\"Čerpadlová (Rezidence Harfistka)\",\"postal_code\":\"\"}}}', 0, NULL, 'gopay', 3287986715, 'paid', 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, 0, 0.00, 1447.00, 0, NULL),
(134, NULL, 'KJD-2025-2844', 'mickeyjarolim4@gmail.com', '678687867', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', '190 00', 749.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-46EABF0B', '2025-12-08 08:19:40', '', '{\"100-product-Oranžová--\":{\"id\":100,\"product_type\":\"product\",\"name\":\"Lampa- Shroom\",\"price\":649,\"final_price\":649,\"selected_color\":\"Oranžová\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1E8JlrAizaRqho2E0tMRBuyhu55J1YQqL\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]}}', 0, NULL, 'gopay', 9201797334, 'pending', 100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 749.00, 0, NULL),
(135, NULL, 'KJD-2025-0915', 'mickeyjarolim4@gmail.com', '867687867', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', '190 00', 1298.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-E4A6B830', '2025-12-08 08:48:28', '', '{\"100-product-Oranžová--\":{\"id\":100,\"product_type\":\"product\",\"name\":\"Lampa- Shroom\",\"price\":649,\"final_price\":649,\"selected_color\":\"Oranžová\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1E8JlrAizaRqho2E0tMRBuyhu55J1YQqL\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"102-product---b3b16a5b82ffc95c6fe8974915760a65\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Modrá\",\"Spodní část\":\"Modrá\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', 9201808539, 'pending', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 1298.00, 0, NULL),
(136, NULL, 'KJD-2025-7631', 'mickeyjarolim4@gmail.com', '678867867', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', '190 00', 1298.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-B8B9056D', '2025-12-08 08:55:44', '', '{\"100-product-Oranžová--\":{\"id\":100,\"product_type\":\"product\",\"name\":\"Lampa- Shroom\",\"price\":649,\"final_price\":649,\"selected_color\":\"Oranžová\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1E8JlrAizaRqho2E0tMRBuyhu55J1YQqL\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"102-product---b3b16a5b82ffc95c6fe8974915760a65\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Modrá\",\"Spodní část\":\"Modrá\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', 9201811535, 'pending', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 1298.00, 0, NULL),
(137, NULL, 'KJD-2025-5128', 'mickeyjarolim3@gmail.com', '7', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Čerpadlová 286/2 , 190 00 Praha', '190 00', 1298.00, 'Přijato', NULL, NULL, NULL, 0, 'TRK-B104103A', '2025-12-08 09:06:40', '', '{\"100-product-Oranžová--\":{\"id\":100,\"product_type\":\"product\",\"name\":\"Lampa- Shroom\",\"price\":649,\"final_price\":649,\"selected_color\":\"Oranžová\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1E8JlrAizaRqho2E0tMRBuyhu55J1YQqL\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"102-product---b3b16a5b82ffc95c6fe8974915760a65\":{\"id\":102,\"product_type\":\"product\",\"name\":\"Lampa- WAVEA\",\"price\":649,\"final_price\":649,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":{\"Horní část\":\"Modrá\",\"Spodní část\":\"Modrá\"},\"catalog_selection\":[]}}', 0, NULL, 'gopay', 9201815946, 'pending', 0.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 0.00, 1298.00, 0, NULL),
(138, NULL, 'KJD-2025-5426', 'mickeyjarolim4@gmail.com', '2', 'Jakub Jarolim', 'Zásilkovna', '26537', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)', 'Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Vysočany, Čerpadlová (Rezidence Harfistka)', '190 00', 1.00, 'ready_for_pickup', NULL, NULL, NULL, 0, 'TRK-EB46A72F', '2025-12-08 09:54:56', '[Zásilkovna] Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka), Vysočany, Čerpadlová (Rezidence Harfistka)', '{\"107-product---\":{\"id\":107,\"product_type\":\"product\",\"name\":\"TEST\",\"price\":1,\"final_price\":1,\"selected_color\":\"\",\"color_price\":0,\"quantity\":1,\"image_url\":\"\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"_delivery_info\":{\"zasilkovna\":{\"name\":\"Z-BOX Praha 9, Vysočany, Čerpadlová (Rezidence Harfistka)\",\"street\":\"Vysočany\",\"city\":\"Čerpadlová (Rezidence Harfistka)\",\"postal_code\":\"\"}}}', 0, NULL, 'gopay', 9201835495, 'pending', 0.00, NULL, NULL, NULL, '', NULL, NULL, NULL, 0, 0.00, 1.00, 0, NULL),
(139, NULL, 'KJD-2025-6112', 'risa.stangl@gmail.com', '608484554', 'Richard Štangl', 'Zásilkovna', '31226', 'Z-BOX Praha 8, Libeň, Na Dědince', 'Z-BOX Praha 8, Libeň, Na Dědince, Libeň, Na Dědince', '180 00', 664.40, 'processing', NULL, NULL, NULL, 0, 'TRK-1F11F245', '2025-12-10 07:52:26', '[Zásilkovna] Z-BOX Praha 8, Libeň, Na Dědince, Libeň, Na Dědince\nKadim mámě do kalhotek', '{\"29-product-#ff0000--\":{\"id\":29,\"product_type\":\"product\",\"name\":\"Květináč- Verde Spiral\",\"price\":80,\"final_price\":64,\"selected_color\":\"#ff0000\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/14szYgPVKR3XVBgqWOSknHEWyiEty6ztT\",\"variants\":[],\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"25-product-#d3d3d3-3a8ca14e0540307bc82e4b2f88418486-\":{\"id\":25,\"product_type\":\"product\",\"name\":\"Lampa- Aurora\",\"price\":600,\"final_price\":600,\"selected_color\":\"#d3d3d3\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/19x819BwwnmjHwVh0A1s8nY8WkY3-qeps\",\"variants\":{\"Barva nožiček\":\"Modrá\"},\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"_delivery_info\":{\"zasilkovna\":{\"name\":\"Z-BOX Praha 8, Libeň, Na Dědince\",\"street\":\"Libeň\",\"city\":\"Na Dědince\",\"postal_code\":\"\"}}}', 0, NULL, 'gopay', 9202726619, 'paid', 100.00, NULL, NULL, NULL, '', NULL, NULL, NULL, 0, 0.00, 664.40, 0, NULL),
(140, NULL, 'KJD-2025-7142', 'pruso.magdalena@gmail.com', '773623088', 'Magdalena Pruso', 'Zásilkovna', '30826', 'Z-BOX Praha 8, Karlín, Rohanské nábřeží 672/13', 'Z-BOX Praha 8, Karlín, Rohanské nábřeží 672/13, Karlín, Rohanské nábřeží 672/13', '180 00', 700.00, 'shipped', NULL, NULL, NULL, 0, 'TRK-F593C750', '2025-12-15 08:11:18', '[Zásilkovna] Z-BOX Praha 8, Karlín, Rohanské nábřeží 672/13, Karlín, Rohanské nábřeží 672/13', '{\"24-product-#ffd700-3f5e669b885020b08059b435f7b21d6b-\":{\"id\":24,\"product_type\":\"product\",\"name\":\"Lampa- Spirála světla\",\"price\":600,\"final_price\":600,\"selected_color\":\"#ffd700\",\"color_price\":0,\"quantity\":1,\"image_url\":\"https:\\/\\/lh3.googleusercontent.com\\/d\\/1PJWon9gV28QkhDBfiQbkD7fQnDwE0Pqx\",\"variants\":{\"Barva nožiček\":\"Bílá\"},\"variant_price_adjustment\":0,\"component_colors\":[],\"catalog_selection\":[]},\"_delivery_info\":{\"zasilkovna\":{\"name\":\"Z-BOX Praha 8, Karlín, Rohanské nábřeží 672\\/13\",\"street\":\"Karlín\",\"city\":\"Rohanské nábřeží 672\\/13\",\"postal_code\":\"\"}}}', 0, NULL, 'gopay', 9205157706, 'paid', 100.00, NULL, NULL, NULL, '', NULL, NULL, NULL, 0, 0.00, 700.00, 0, NULL);

-- --------------------------------------------------------

--
-- Struktura tabulky `order_cancellations`
--

CREATE TABLE `order_cancellations` (
  `id` int NOT NULL,
  `order_id` int DEFAULT NULL,
  `order_number` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text NOT NULL,
  `reason` text NOT NULL,
  `additional_info` text,
  `products` text NOT NULL,
  `status` enum('pending','processing','completed','rejected') NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Vypisuji data pro tabulku `order_cancellations`
--

INSERT INTO `order_cancellations` (`id`, `order_id`, `order_number`, `name`, `email`, `phone`, `address`, `reason`, `additional_info`, `products`, `status`, `created_at`, `updated_at`) VALUES
(1, 86, '2025082041059', ' ', 'mickeyjarolim3@gmail.com', '', 'j', 'Změna názoru', NULL, '[\"{\\\"id\\\":100,\\\"name\\\":\\\"Lampa- Shroom\\\",\\\"price\\\":\\\"600 K\\\\u010d\\\",\\\"image\\\":\\\"uploads\\\\\\/products\\\\\\/689d191a31713_SHROOM.png\\\",\\\"quantity\\\":1,\\\"color\\\":\\\"\\\\u017dlut\\\\u00e1\\\"}\"]', 'pending', '2025-11-08 13:01:23', NULL),
(2, 86, '2025082041059', ' ', 'mickeyjarolim3@gmail.com', '', 'j', 'Jiný důvod', NULL, '[\"{\\\"id\\\":100,\\\"name\\\":\\\"Lampa- Shroom\\\",\\\"price\\\":\\\"600 K\\\\u010d\\\",\\\"image\\\":\\\"uploads\\\\\\/products\\\\\\/689d191a31713_SHROOM.png\\\",\\\"quantity\\\":1,\\\"color\\\":\\\"\\\\u017dlut\\\\u00e1\\\"}\"]', 'pending', '2025-11-08 14:03:17', NULL),
(3, 86, '2025082041059', ' ', 'mickeyjarolim3@gmail.com', '', 'j', 'Jiný důvod', NULL, '[\"{\\\"id\\\":100,\\\"name\\\":\\\"Lampa- Shroom\\\",\\\"price\\\":\\\"600 K\\\\u010d\\\",\\\"image\\\":\\\"uploads\\\\\\/products\\\\\\/689d191a31713_SHROOM.png\\\",\\\"quantity\\\":1,\\\"color\\\":\\\"\\\\u017dlut\\\\u00e1\\\"}\"]', 'pending', '2025-11-08 14:04:23', NULL);

-- --------------------------------------------------------

--
-- Struktura tabulky `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` int NOT NULL,
  `order_id` int NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `order_status_history`
--

INSERT INTO `order_status_history` (`id`, `order_id`, `status`, `created_at`) VALUES
(1, 140, 'ready_for_pickup', '2025-12-22 01:31:23'),
(2, 138, 'ready_for_pickup', '2025-12-22 01:32:28'),
(3, 140, 'delivered', '2025-12-23 00:13:15');

-- --------------------------------------------------------

--
-- Struktura tabulky `print_calculations`
--

CREATE TABLE `print_calculations` (
  `id` int NOT NULL,
  `product_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filament_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filament_2_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `weight` decimal(10,2) NOT NULL,
  `weight_2` decimal(10,2) DEFAULT '0.00',
  `other_costs` decimal(10,2) NOT NULL,
  `material_cost` decimal(10,2) NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `selling_price` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `margin` decimal(10,2) NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Vypisuji data pro tabulku `print_calculations`
--

INSERT INTO `print_calculations` (`id`, `product_name`, `filament_name`, `filament_2_name`, `weight`, `weight_2`, `other_costs`, `material_cost`, `total_cost`, `selling_price`, `profit`, `margin`, `note`, `created_at`) VALUES
(2, 'Lampa- WAVEA', 'Alzament PLA Black', 'Elegoo PETG Transparent', 65.00, 199.00, 117.00, 58.04, 175.04, 649.00, 473.96, 73.03, NULL, '2025-11-22 12:37:10');

-- --------------------------------------------------------

--
-- Struktura tabulky `product`
--

CREATE TABLE `product` (
  `id` int NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `model` varchar(100) COLLATE utf8mb4_bin DEFAULT NULL,
  `availability` varchar(50) COLLATE utf8mb4_bin DEFAULT NULL,
  `slug` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `html_tech_specs` mediumtext COLLATE utf8mb4_bin,
  `price` decimal(10,2) NOT NULL,
  `colors` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `unavailable_colors` text COLLATE utf8mb4_bin,
  `no_color_required` tinyint(1) DEFAULT '0',
  `color_components` text COLLATE utf8mb4_bin,
  `component_images` text COLLATE utf8mb4_bin,
  `image_url` text COLLATE utf8mb4_bin,
  `available_from` datetime DEFAULT NULL,
  `stock_status` enum('in_stock','out_of_stock','preorder') COLLATE utf8mb4_bin DEFAULT 'in_stock',
  `is_preorder` tinyint(1) DEFAULT '0',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `color_prices` text COLLATE utf8mb4_bin,
  `variants` json DEFAULT NULL,
  `release_date` date DEFAULT NULL,
  `sale_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sale_price` decimal(10,2) DEFAULT NULL,
  `sale_start` datetime DEFAULT NULL,
  `sale_end` datetime DEFAULT NULL,
  `is_hidden` tinyint(1) NOT NULL DEFAULT '0',
  `baleni` text COLLATE utf8mb4_bin,
  `is_lamp_config` tinyint(1) DEFAULT '0',
  `model_3d_path` varchar(255) COLLATE utf8mb4_bin DEFAULT NULL COMMENT '3D model cesta (GLB soubor pro AR viewer)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

--
-- Vypisuji data pro tabulku `product`
--

INSERT INTO `product` (`id`, `name`, `model`, `availability`, `slug`, `description`, `html_tech_specs`, `price`, `colors`, `unavailable_colors`, `no_color_required`, `color_components`, `component_images`, `image_url`, `available_from`, `stock_status`, `is_preorder`, `updated_at`, `color_prices`, `variants`, `release_date`, `sale_enabled`, `sale_price`, `sale_start`, `sale_end`, `is_hidden`, `baleni`, `is_lamp_config`, `model_3d_path`) VALUES
(1, 'Květináč- Vlnka', '', 'Skladem', NULL, '<p>Skvěl&yacute; jako d&aacute;rek. Rozměry: 16cm na v&yacute;&scaron;ku, 10cm na &scaron;&iacute;řku</p>', '', 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1bvFs-PV34Vzi0JRotucuVSSi16Rk-oU8,https://lh3.googleusercontent.com/d/1hnhLm70WGCXVfYqzaZRWTPTfAqG-JCwb,https://lh3.googleusercontent.com/d/15tqZ1I_XsAswl5q07YR2ZAYZmJ25wvau,https://lh3.googleusercontent.com/d/1RoE4Da4hdMFC8aSYAl7pcZDaEpKWpUzF,https://lh3.googleusercontent.com/d/1hr2fDSjyD4h-gG4lndo2k3cpJcF-2sd3,https://lh3.googleusercontent.com/d/1mp5d4Wj0PIHFCOwCvhEZ5yVk38KOpST2', '2025-12-02 09:00:00', 'in_stock', 0, '2025-12-02 08:20:43', NULL, '[]', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(2, 'Květináč- Vlna jemnosti ', NULL, 'Skladem', NULL, 'Rozměry: 16cm na výšku, 10cm na šířku', NULL, 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', '', 0, NULL, NULL, 'https://lh3.googleusercontent.com/d/1UG2pKscKNIb0KuR7JaBdGGoPOgIEV_Gh,https://lh3.googleusercontent.com/d/1nYe47wfNzVC0Qrzwbe0x-tp6sqqh59be,https://lh3.googleusercontent.com/d/1XqdYZU7NqvGtYEeQ52mg7OUh_lXkB6r5,https://lh3.googleusercontent.com/d/1XWQWGkT5BMzWDdGx9umCQCF0z8H4gwir,https://lh3.googleusercontent.com/d/1dHUR4EueiXpueU7SoI4Ci7KXbm_E_Pqa,https://lh3.googleusercontent.com/d/10TLGpkoQplCaDVRzImcTSfhFRMgra4Vv', '2025-12-02 09:00:00', 'in_stock', 0, '2025-12-02 08:24:39', NULL, NULL, NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(3, 'Bubble Podtácky', '', 'Skladem', NULL, '<p>Super podt&aacute;cky pod va&scaron;e hrnečky/skleničky. 4ks</p>', '', 120.00, NULL, NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1V4OEBAOv9PpuGaoR3Tz8Z3gr6AI19zix,https://lh3.googleusercontent.com/d/1sSs3tf3RFBcsi9Ps4xVUotUWM-x-OtQ1', '2025-11-10 10:45:00', 'in_stock', 0, '2025-12-03 07:16:03', NULL, '{\"Velikost\": {\"Malé\": {\"price\": 0, \"stock\": 100}, \"Velké\": {\"price\": 30, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(6, 'Lampa- Háčkovaná záře', '', 'Skladem', NULL, '<p>Lampička m&aacute; z&aacute;vit E14</p>', '', 599.00, '#ff0000, #ffff00, #00eaff', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1-LX4ziiXHQhornIQnL9xvUUGHaBMbbuV,https://lh3.googleusercontent.com/d/18jOHXnaPELa6jdBBKMYhiI5HxwVlvaYB,https://lh3.googleusercontent.com/d/1uChZpuyqJcEZB68EIqcAevh205WYCw7r', '2025-03-03 09:09:00', 'in_stock', 0, '2025-12-03 07:14:48', NULL, '[]', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(7, 'Lampa- Orbitální elegance', NULL, 'Skladem', NULL, 'Dodejte svému interiéru jedinečný dotek s lampou Orbitální elegance. Tento moderní designový kousek zaujme svým vrstevnatým tvarem inspirovaným vesmírnými dráhami a minimalismem.\r\n\r\nLampa je ideální volbou pro milovníky čistého a nadčasového designu, který kombinuje eleganci s funkčností. Černé nožky zajišťují stabilitu a dodávají lampě moderní kontrast k jemnému tónu stínidla.\r\n\r\nPerfektně se hodí jako stylový doplněk do obývacího pokoje, ložnice nebo pracovny. Její příjemné světlo vytvoří pohodovou atmosféru a stane se dominantním prvkem každého prostoru.\r\n\r\nSpecifikace:\r\n	•	Výška lampy: 160 mm\r\n	•	Materiál stínidla: Transparentní PETG\r\n	•	Barva nožek: Matná černá\r\n	•	Použití: Vhodná pro LED žárovky\r\n\r\nVyberte si Orbitální eleganci a přidejte do svého domova harmonii designu a světla!', NULL, 600.00, '#ffd700', NULL, 0, NULL, NULL, '', '2025-12-02 09:00:00', 'in_stock', 0, '2025-12-02 08:24:41', NULL, NULL, NULL, 0, NULL, NULL, NULL, 1, NULL, 0, NULL),
(10, 'Voucher', NULL, 'Skladem', NULL, '<div style=\"font-family: Helvetica, Arial, sans-serif; color: #0a0a0a; background-color: #fdfdfd; letter-spacing: 0.6px; line-height: 1.6;\">\r\n<h2 style=\"font-size: 1.4em;\"><strong>Digit&aacute;ln&iacute; Voucher</strong> na v&scaron;echny produkty z <a style=\"color: #000000; text-decoration: underline;\" href=\"https://kubajadesigns.eu/index.php#portfolio-section\" target=\"_blank\" rel=\"noopener\">kubajadesigns.eu</a></h2>\r\n<p>Darujte kreativitu! Vyberte si libovolnou hodnotu a obdarujte sv&eacute; bl&iacute;zk&eacute; origin&aacute;ln&iacute;m d&aacute;rkem &ndash; ide&aacute;ln&iacute; na V&aacute;noce, narozeniny nebo když si nejste jisti v&yacute;běrem.</p>\r\n<p><strong style=\"color: #b80000;\">Voucher neplat&iacute; na dopravu!</strong></p>\r\n<hr style=\"border: none; border-top: 1px solid #ddd; margin: 20px 0;\">\r\n<h3 style=\"text-decoration: underline;\">? JAK TO FUNGUJE?</h3>\r\n<ol>\r\n<li>Vyberete si hodnotu d&aacute;rkov&eacute; karty a provedete platbu předem.</li>\r\n<li>Do 24 hodin obdrž&iacute;te d&aacute;rkovou kartu s unik&aacute;tn&iacute;m k&oacute;dem na e-mail.</li>\r\n<li>Po přihl&aacute;&scaron;en&iacute; ke sv&eacute;mu &uacute;čtu kartu jednodu&scaron;e aktivujete.</li>\r\n<li>V <a href=\"https://kubajadesigns.eu/view_cart.php\" target=\"_blank\" rel=\"noopener\">ko&scaron;&iacute;ku</a> využijete kredit z karty.</li>\r\n</ol>\r\n<hr style=\"border: none; border-top: 1px solid #ddd; margin: 20px 0;\">\r\n<h3 style=\"text-decoration: underline;\">❓ ČAST&Eacute; DOTAZY</h3>\r\n<p><strong>Co když je hodnota objedn&aacute;vky vy&scaron;&scaron;&iacute; než č&aacute;stka na voucheru?</strong><br>&rarr; Č&aacute;stka z voucheru se odečte a zbytek pohodlně doplat&iacute;te.</p>\r\n<p><strong>Co když je hodnota objedn&aacute;vky niž&scaron;&iacute; než č&aacute;stka na voucheru?</strong><br>&rarr; Odečte se pouze cena objedn&aacute;vky, zbytek zůstane na voucheru pro dal&scaron;&iacute; n&aacute;kup.</p>\r\n<p><em>Voucher funguje jako kreditn&iacute; karta &ndash; č&aacute;stku můžete čerpat postupně, nen&iacute; nutn&eacute; ji vyčerpat najednou.</em></p>\r\n<p><strong>Kdy voucher vypr&scaron;&iacute;?</strong><br>&rarr; Nikdy &ndash; platnost je neomezen&aacute; od okamžiku aktivace.</p>\r\n</div>', '', 100.00, NULL, NULL, 0, NULL, NULL, 'https://drive.google.com/file/d/1crmfPVjA1GY9rzNTch7WjHhtn0j_BJ7x/view?usp=sharing', '2025-05-01 14:51:00', 'in_stock', 0, '2025-12-02 09:03:54', '{\"Univerzální\":0}', '{\"Hodnota\": {\"100\": {\"price\": 0, \"stock\": 100}, \"200\": {\"price\": 100, \"stock\": 100}, \"300\": {\"price\": 200, \"stock\": 100}, \"400\": {\"price\": 300, \"stock\": 100}, \"500\": {\"price\": 400, \"stock\": 100}, \"600\": {\"price\": 500, \"stock\": 100}, \"700\": {\"price\": 600, \"stock\": 100}, \"800\": {\"price\": 700, \"stock\": 100}, \"900\": {\"price\": 800, \"stock\": 100}, \"1000\": {\"price\": 900, \"stock\": 100}, \"1100\": {\"price\": 1000, \"stock\": 100}, \"1200\": {\"price\": 1100, \"stock\": 100}, \"1300\": {\"price\": 1200, \"stock\": 100}, \"1400\": {\"price\": 1300, \"stock\": 100}, \"1500\": {\"price\": 1400, \"stock\": 100}, \"1600\": {\"price\": 1500, \"stock\": 100}, \"1700\": {\"price\": 1600, \"stock\": 100}, \"1800\": {\"price\": 1700, \"stock\": 100}, \"1900\": {\"price\": 1800, \"stock\": 100}, \"2000\": {\"price\": 1900, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(22, 'Lampa- Vlnka světla', '', 'Skladem', NULL, '<p>Elegantn&iacute; stoln&iacute; lampa, kter&aacute; se stane ozdobou každ&eacute;ho interi&eacute;ru. D&iacute;ky sv&eacute;mu unik&aacute;tn&iacute;mu vlnit&eacute;mu designu působ&iacute; moderně a nadčasově. Je ide&aacute;ln&iacute; volbou pro vytvořen&iacute; př&iacute;jemn&eacute; atmosf&eacute;ry v ob&yacute;vac&iacute;m pokoji, ložnici nebo kancel&aacute;ři. Vyrobena z kvalitn&iacute;ho materi&aacute;lu pomoc&iacute; 3D tisku, lampa kombinuje estetiku s funkčnost&iacute;. Rozměry lampy jsou pečlivě navrženy tak, aby se ve&scaron;la na stůl, nočn&iacute; stolek nebo poličku, a z&aacute;roveň poskytovala dostatek světla. Zažijte harmonii tvaru a světla s lampou Vlnka světla!</p>', '', 600.00, NULL, NULL, 0, '[{\"name\":\"Barva vršku\",\"colors\":[\"Žlutá\",\"Bílá\",\"Modrá\",\"Zelená\",\"Fialová\",\"Červená\"],\"required\":true},{\"name\":\"Barva nožiček\",\"colors\":[\"Žlutá\",\"Bílá\",\"Modrá\",\"Zelená\",\"Fialová\",\"Červená\",\"Černá\"],\"required\":true}]', '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1Ex2lOuCQYOJ19_NF3nZjomwDxX7A1HKB,https://lh3.googleusercontent.com/d/1YaG8rVZaRHDqPdI5xV-02u9t7AzOiVUx', '2025-10-07 13:52:00', 'in_stock', 0, '2026-01-04 23:14:02', NULL, '[]', NULL, 1, 510.00, '2026-01-05 00:15:00', '2026-01-10 00:13:00', 0, NULL, 0, NULL),
(24, 'Lampa- Spirála světla', '', 'Skladem', NULL, '<p>Spir&aacute;la světla je modern&iacute; lampa, kter&aacute; jemn&yacute;mi spir&aacute;lov&yacute;mi liniemi přin&aacute;&scaron;&iacute; do va&scaron;eho interi&eacute;ru př&iacute;jemn&eacute; světeln&eacute; efekty. Je vyrobena s využit&iacute;m 3D tisku a kvalitn&iacute;ch materi&aacute;lů, takže se hod&iacute; jak do obytn&yacute;ch, tak pracovn&iacute;ch prostor. Lampa je kompatibiln&iacute; se ž&aacute;rovkami E14, což v&aacute;m umožn&iacute; přizpůsobit si osvětlen&iacute; podle vlastn&iacute;ch představ. Praktick&yacute; čern&yacute; kabel o d&eacute;lce 3 metry zaji&scaron;ťuje flexibilitu um&iacute;stěn&iacute; a snadnou instalaci v různ&yacute;ch prostor&aacute;ch. Vyzkou&scaron;ejte Spir&aacute;lu světla a dopřejte sv&eacute;mu domovu jednoduch&yacute;, ale stylov&yacute; prvek osvětlen&iacute;.</p>', '', 600.00, '#ffd700', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1PJWon9gV28QkhDBfiQbkD7fQnDwE0Pqx,https://lh3.googleusercontent.com/d/16GWqQYE-LldrjUSoApROD3-C43krbzH2', '2025-12-02 09:00:00', 'in_stock', 0, '2026-01-04 23:13:43', NULL, '{\"Barva nožiček\": {\"Bílá\": {\"price\": 0, \"stock\": 100}, \"Modrá\": {\"price\": 0, \"stock\": 100}, \"Žlutá\": {\"price\": 0, \"stock\": 100}, \"Červená\": {\"price\": 0, \"stock\": 100}}}', NULL, 1, 510.00, '2026-01-05 00:15:00', '2026-01-10 00:13:00', 0, NULL, 0, NULL),
(25, 'Lampa- Aurora', '', 'Skladem', NULL, '<ul>\r\n<li><u><span style=\"font-weight: bolder;\" data-start=\"118\" data-end=\"146\">Jedinečn&yacute; organick&yacute; tvar</span></u>&nbsp;&ndash; přin&aacute;&scaron;&iacute; harmonii a pohodu do každ&eacute;ho interi&eacute;ru.</li>\r\n<li><span style=\"font-weight: bolder;\" data-start=\"202\" data-end=\"225\"><u>Ekologick&yacute; materi&aacute;l</u></span>&nbsp;&ndash; vyroben&aacute; z PLA a PETG &scaron;etrn&eacute;ho k př&iacute;rodě.</li>\r\n<li><span style=\"font-weight: bolder;\" data-start=\"268\" data-end=\"289\"><u>Kr&aacute;sn&aacute; hra světla</u></span>&nbsp;&ndash; jemn&eacute; linie umožňuj&iacute; př&iacute;jemn&eacute; a &uacute;tuln&eacute; osvětlen&iacute;.</li>\r\n<li><span style=\"font-weight: bolder;\" data-start=\"347\" data-end=\"365\"><u>Skryt&aacute; obj&iacute;mka</u></span>&nbsp;&ndash; čist&yacute; design, viditeln&yacute; zůst&aacute;v&aacute; pouze čern&yacute; kabel (d&eacute;lka 3 m).</li>\r\n<li><span style=\"font-weight: bolder;\" data-start=\"436\" data-end=\"459\"><u>Univerz&aacute;ln&iacute; použit&iacute;</u></span>&nbsp;&ndash; ide&aacute;ln&iacute; na nočn&iacute; stolek, pracovn&iacute; stůl či do ob&yacute;vac&iacute;ho pokoje.</li>\r\n<li><span style=\"font-weight: bolder;\" data-start=\"530\" data-end=\"563\"><u>Kompatibiln&iacute; se ž&aacute;rovkami E14</u></span>&nbsp;&ndash; v&yacute;&scaron;ka lampy cca 15&ndash;20 cm.</li>\r\n</ul>\r\n<p class=\"\" data-start=\"595\" data-end=\"675\">&nbsp; &nbsp; Objevte kouzlo světla s lampou Aurora a proměňte svůj domov v o&aacute;zu pohody!&nbsp;</p>', '', 600.00, '#d3d3d3, #ffffff', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/19x819BwwnmjHwVh0A1s8nY8WkY3-qeps,https://lh3.googleusercontent.com/d/1NgVh8FiKOMmX2s8mnYhueu_v0dafN-x7,https://lh3.googleusercontent.com/d/19uzz3jVLd55s0IXCLN5oS_vWm614w4bC', '2025-03-03 00:00:00', 'in_stock', 0, '2025-12-17 10:21:56', NULL, '{\"Barva nožiček\": {\"Bílá\": {\"price\": 0, \"stock\": 100}, \"Modrá\": {\"price\": 0, \"stock\": 100}, \"Žlutá\": {\"price\": 0, \"stock\": 100}, \"Červená\": {\"price\": 0, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(28, 'Sada z jarní edice květináčů', '', 'Skladem', NULL, '<p>Pořiďte si sadu květin&aacute;čů za zv&yacute;hodněnou cenu!</p>\r\n<p><strong>Barvy květin&aacute;čů budou různ&eacute;. Pokud si chcete vybrat konkr&eacute;tn&iacute; barvy, napi&scaron;te je do pozn&aacute;mky při platbě.</strong></p>', '', 350.00, '#ffd700', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1Ce6gTrmE0rjuy9ipRx0NkaN-D39Wl7nw', '2025-03-13 16:00:00', 'in_stock', 0, '2025-12-03 07:21:37', NULL, '[]', NULL, 1, 279.00, NULL, '2025-12-24 00:00:00', 0, NULL, 0, NULL),
(29, 'Květináč- Verde Spiral', '', 'Skladem', NULL, '<p>Elegantn&iacute; květin&aacute;č s jemn&yacute;m spir&aacute;lov&yacute;m vzorem, kter&yacute; dod&aacute; va&scaron;emu interi&eacute;ru jedinečn&yacute; styl. Skvěle se hod&iacute; pro různ&eacute; druhy rostlin a je dostupn&yacute; v několika barevn&yacute;ch variant&aacute;ch, takže si snadno vyberete tu pravou.</p>', '', 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/14szYgPVKR3XVBgqWOSknHEWyiEty6ztT', '2025-03-13 16:00:00', 'in_stock', 0, '2025-12-17 10:21:58', NULL, '[]', NULL, 1, 64.00, NULL, '2025-12-24 00:00:00', 0, NULL, 0, 'models/29.glb'),
(30, 'Květináč- Flamma', '', 'Skladem', NULL, '<p>Modern&iacute; květin&aacute;č s dynamick&yacute;m designem, kter&yacute; přin&aacute;&scaron;&iacute; energii a styl do jak&eacute;hokoli prostoru. D&iacute;ky &scaron;irok&eacute; &scaron;k&aacute;le barev si můžete vybrat takovou, kter&aacute; nejl&eacute;pe zapadne do va&scaron;eho interi&eacute;ru.</p>', '', 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', NULL, 0, NULL, NULL, '', '2025-03-13 16:00:00', 'in_stock', 0, '2025-12-17 10:00:23', NULL, '[]', NULL, 1, 64.00, NULL, '2025-12-24 00:00:00', 0, NULL, 0, 'models/30.glb'),
(31, 'Květináč- Azure Wave', '', 'Skladem', NULL, '<p>Květin&aacute;č inspirovan&yacute; jemn&yacute;mi vlnami, kter&yacute; dod&aacute;v&aacute; harmonii a eleganci každ&eacute; rostlině. K dispozici v různ&yacute;ch barevn&yacute;ch variant&aacute;ch, takže si můžete vybrat ten, kter&yacute; v&aacute;m nejv&iacute;ce vyhovuje.</p>', '', 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', NULL, '2025-03-13 16:00:00', 'in_stock', 0, '2025-12-02 07:33:41', NULL, '[]', NULL, 1, 64.00, NULL, '2025-12-24 00:00:00', 0, NULL, 0, NULL),
(32, 'Květináč- zatočený květ', '', 'Skladem', NULL, '<p>Nechte se okouzlit jedinečn&yacute;m designem květin&aacute;če \"Zatočen&yacute; květ\". Jeho hrav&yacute; tvar a &scaron;irok&aacute; paleta barev dodaj&iacute; va&scaron;im rostlin&aacute;m ten spr&aacute;vn&yacute; &scaron;mrnc.</p>', '', 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', NULL, 0, NULL, NULL, '', '2025-03-13 16:00:00', 'in_stock', 0, '2025-11-28 08:23:24', NULL, '[]', NULL, 1, 64.00, NULL, '2025-12-24 00:00:00', 0, NULL, 0, NULL),
(33, 'Květináč- Jarní poupě', '', 'Skladem', 'kvetinac-jarni-poupe', '<p>Oživte svůj domov s př&iacute;chodem jara! Květin&aacute;č \'<span style=\"background-color: rgb(107, 165, 74);\">Zatočen&yacute; květ</span>\' v &scaron;irok&eacute; paletě barev přin&aacute;&scaron;&iacute; do va&scaron;eho interi&eacute;ru svěžest a energii probouzej&iacute;c&iacute; se př&iacute;rody. Vyberte si z na&scaron;ich jarn&iacute;ch odst&iacute;nů ten, kter&yacute; nejl&eacute;pe lad&iacute; s va&scaron;&iacute;m stylem, a nechte sv&eacute; rostliny rozkv&eacute;st v nov&eacute;m kab&aacute;tě.</p>', '', 80.00, '#ff0000, #ffff00, #ffd1dc, #a7c17a, #00eaff, #d2b48c, #ffffff', NULL, 0, NULL, NULL, '', '2025-03-13 16:00:00', 'in_stock', 0, '2025-11-28 08:25:06', NULL, '[]', NULL, 1, 64.00, NULL, '2025-12-24 00:00:00', 0, NULL, 0, NULL),
(86, 'Mechová tyč pro monsteru', NULL, 'Skladem', NULL, '<p data-start=\"39\" data-end=\"279\" class=\"\">Dopřejte své monsteře tu nejlepší oporu! Tato <strong data-start=\"95\" data-end=\"110\"><font color=\"#e76363\">mechová tyč</font></strong> je navržena pro zdravý růst vašich popínavých rostlin. Díky skvělému designu s <strong data-start=\"194\" data-end=\"220\"><font color=\"#e76363\">propletenou strukturou</font></strong> poskytuje dokonalý povrch pro uchycení vzdušných kořenů.</p><ul><li><strong data-start=\"284\" data-end=\"304\" style=\"font-size: 1rem;\"><font color=\"#e76363\">Modulární systém</font></strong><span style=\"font-size: 1rem;\"> – snadno přidáte další díly a prodloužíte tyč podle potřeby.</span></li><li><font color=\"#e76363\"><strong data-start=\"371\" data-end=\"391\" data-is-only-node=\"\">Snadná instalace</strong> </font>– ostrý hrot umožňuje pevné ukotvení do substrátu.</li><li><strong data-start=\"448\" data-end=\"476\"><font color=\"#e76363\">Stylový a funkční design</font></strong> – skvěle zapadne do každého interiéru.</li></ul><p>\r\n\r\n</p><p data-start=\"519\" data-end=\"589\" class=\"\">Dejte své rostlině přirozenou podporu a sledujte, jak se mění!&nbsp;<br><br><u>Startovací balíček 1+1</u> (2x tyč, 1x zápich, 1x víčko)</p><p data-start=\"519\" data-end=\"589\" class=\"\"><u>Samotná tyč</u> (pouze jen tyč pez zápichu a víčka)</p><p data-start=\"519\" data-end=\"589\" class=\"\"><br></p>', NULL, 90.00, '#000000, #a7c17a, #ffd1dc, #ffffff', '', 0, NULL, NULL, 'https://drive.google.com/file/d/1A8kG7pZAqlBVk7bSpLDWLacQxPHuuo96/view?usp=sharing, https://drive.google.com/file/d/1UrZi04UJKzl8pqT_IfmkSCY84j4FQGr_/view?usp=sharing', '2025-03-28 18:00:00', 'in_stock', 0, '2025-12-02 07:30:31', '{\"#000000\":0,\"#a7c17a\":0,\"#ffd1dc\":0,\"#ffffff\":0}', '{\"Typ\": {\"Samotná tyč\": {\"price\": -50, \"stock\": 100}, \"Tyč+ zápich+ víčko\": {\"price\": 0, \"stock\": 100}, \"Startovací balíček 1+1\": {\"price\": 90, \"stock\": 100}}, \"Velikost\": {\"Malá\": {\"price\": 0, \"stock\": 100}, \"Velká\": {\"price\": 0, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(87, 'Monstera přívěšky', NULL, 'Skladem', NULL, '<p data-start=\"32\" data-end=\"83\" class=\"\">Stylové přívěsky inspirované krásou monster! </p><ul><li><strong data-start=\"87\" data-end=\"109\"><font color=\"#e76363\">4 unikátní designy</font></strong> – můžete si vybrat svůj oblíbený nebo rovnou celý set.</li><li><strong data-start=\"169\" data-end=\"196\" data-is-only-node=\"\"><font color=\"#e76363\" style=\"\">Lehký a odolný materiál</font></strong> – ideální na klíče, batoh nebo jako ozdoba.</li><li><strong data-start=\"245\" data-end=\"270\"><font color=\"#e76363\">Pro milovníky rostlin</font></strong> – skvělý doplněk pro každého, kdo miluje přírodu.</li></ul><p>\r\n\r\n</p><p data-start=\"324\" data-end=\"397\" class=\"\">Vyberte si <strong data-start=\"335\" data-end=\"353\"><font color=\"#e76363\">jeden přívěsek</font></strong> nebo <font color=\"#e76363\"><strong data-start=\"359\" data-end=\"393\">zvýhodněný set všech 4 designů</strong>! </font></p>', NULL, 30.00, 'Univerzální', '', 0, NULL, NULL, '', '2025-03-28 18:00:00', 'in_stock', 0, '2025-11-28 08:25:14', '{\"Univerzální\":0}', '{\"Typ\": {\"Jeden kus\": {\"price\": 0, \"stock\": 100}, \"4 kusy (Všechny 4 budou stejné)\": {\"price\": 70, \"stock\": 100}, \"Balení po 4 kusech od každého jednoho typu\": {\"price\": 70, \"stock\": 100}}, \"Varianta\": {\"#1\": {\"price\": 0, \"stock\": 100}, \"#2\": {\"price\": 0, \"stock\": 100}, \"#3\": {\"price\": 0, \"stock\": 100}, \"#4\": {\"price\": 0, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(88, 'Podpora pro růst', '', 'Skladem', NULL, '<p class=\"p1\"><span style=\"font-family: -apple-system, BlinkMacSystemFont,;\">Podpořte zdrav&yacute; růst sv&eacute; rostlinky s touto elegantn&iacute; oporou!&nbsp;</span></p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Stabiln&iacute; podpora</strong></span> </span>&ndash; pom&aacute;h&aacute; rostlině růst vzhůru a udržet jej&iacute; kr&aacute;sn&yacute; tvar.</li>\r\n<li><span class=\"s1\" style=\"font-family: -apple-system, BlinkMacSystemFont,;\"><strong><span style=\"color: #e76363;\">Minimalistick&yacute; design</span></strong></span><span style=\"font-family: -apple-system, BlinkMacSystemFont,;\"> &ndash; nen&aacute;padně splyne s rostlinou a z&aacute;roveň ji skvěle dopln&iacute;.</span></li>\r\n<li><span class=\"s1\" style=\"font-family: -apple-system, BlinkMacSystemFont,;\"><strong><span style=\"color: #e76363;\">Odoln&yacute; materi&aacute;l</span></strong></span><span style=\"font-family: -apple-system, BlinkMacSystemFont,;\"> &ndash; lehk&yacute;, ale pevn&yacute;, vhodn&yacute; pro dlouhodob&eacute; použit&iacute;.</span></li>\r\n</ul>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p class=\"p3\">Perfektn&iacute; doplněk pro každ&eacute;ho milovn&iacute;ka rostlin!&nbsp;</p>', '', 50.00, '#000000, #ffffff', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1pmniK4LVRTlK3vq1gCEt32Ed5ldzDb2D', '2025-03-28 18:00:00', 'in_stock', 0, '2025-12-02 07:29:14', '{\"#000000\":0,\"#ffffff\":0}', '[]', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(89, 'Náušnice monstera ', '', 'Skladem', NULL, '<p class=\"p1\">Dodejte sv&eacute;mu outfitu jedinečn&yacute; př&iacute;rodn&iacute; dotek s ručně vyr&aacute;běn&yacute;mi n&aacute;u&scaron;nicemi inspirovan&yacute;mi listy monstery! Lehk&eacute;, elegantn&iacute; a origin&aacute;ln&iacute; &ndash; ide&aacute;ln&iacute; pro milovn&iacute;ky př&iacute;rody a jedinečn&yacute;ch doplňků.</p>\r\n<p class=\"p2\"><span class=\"s1\" style=\"font-family: -apple-system, BlinkMacSystemFont,;\"><strong>&nbsp;</strong></span></p>\r\n<ul>\r\n<li><span class=\"s1\" style=\"font-family: -apple-system, BlinkMacSystemFont,;\"><strong><span style=\"color: #e76363;\">Lehk&eacute; a pohodln&eacute; na no&scaron;en&iacute;</span></strong></span><span style=\"font-family: -apple-system, BlinkMacSystemFont,;\"> &ndash; Skvěl&eacute; i na celodenn&iacute; no&scaron;en&iacute;.</span></li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Ručně vyr&aacute;běn&eacute;</span></strong></span> &ndash; Každ&yacute; kus je origin&aacute;l.</li>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Minimalistick&yacute; a modern&iacute; design</strong></span> </span>&ndash; Perfektn&iacute; pro každou př&iacute;ležitost.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Kvalitn&iacute; materi&aacute;l</span></strong></span> &ndash; Odoln&yacute; a ekologick&yacute;.</li>\r\n</ul>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p class=\"p1\">Vyj&aacute;dřete svůj jedinečn&yacute; styl s těmito n&aacute;dhern&yacute;mi n&aacute;u&scaron;nicemi!</p>', '', 90.00, '#ffd700', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1M08zeiZqPWb0HaInYz6Xi0DvrU8oZSV0', '2025-03-28 18:00:00', 'in_stock', 0, '2025-12-02 07:28:04', '{\"#ffd700\":0}', '[]', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(90, 'Minecraft včelka', '', 'Skladem', NULL, '<p class=\"p1\">Hled&aacute;&scaron; mil&yacute; a origin&aacute;ln&iacute; doplněk na kl&iacute;če nebo batoh? Tato včelka ti vykouzl&iacute; &uacute;směv na tv&aacute;ři! Vznikla pomoc&iacute; 3D tisku a může&scaron; si vybrat, jestli ti přijde <span class=\"s1\"><strong>již sestaven&aacute;</strong></span> nebo jako <span class=\"s1\"><strong>stavebnice na kartě</strong></span>, kde si ji jednodu&scaron;e slož&iacute;&scaron; s&aacute;m.</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p class=\"p3\"><strong><u>Možnosti:</u></strong></p>\r\n<p class=\"p4\">&bull; <span class=\"s1\"><strong>Sestaven&aacute; včelka</strong></span> &ndash; hotov&aacute; a připraven&aacute; k zavě&scaron;en&iacute;</p>\r\n<p class=\"p4\">&bull; <span class=\"s1\"><strong>Na kartě (DIY)</strong></span> &ndash; z&aacute;bavn&eacute; skl&aacute;d&aacute;n&iacute;&nbsp;</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p class=\"p3\"><strong>Balen&iacute; obsahuje:</strong></p>\r\n<p class=\"p4\">&bull; 1x včelka (dle zvolen&eacute; varianty)</p>\r\n<p class=\"p4\">&bull; 1x kovov&yacute; kroužek na kl&iacute;če</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p class=\"p1\">Skvěl&yacute; drobn&yacute; <u>d&aacute;rek</u> pro milovn&iacute;ky Minecraftu</p>', '', 60.00, '#ffd700', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', NULL, '2025-04-20 18:00:00', 'in_stock', 0, '2025-12-02 07:27:22', '{\"#ffd700\":0}', '{\"Možnost\": {\"Sestavená\": {\"price\": 5, \"stock\": 100}, \"Na kartě (DIY)\": {\"price\": 0, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 1, NULL, 0, NULL),
(91, 'Minecraft axolotl', '', 'Skladem', NULL, '<p class=\"p1\">Tento 3D ti&scaron;těn&yacute; axolotl je přesně to, co hled&aacute;&scaron;! Přich&aacute;z&iacute; buď jako hotov&yacute; př&iacute;věsek, nebo jako z&aacute;bavn&aacute; stavebnice, kterou si může&scaron; s&aacute;m složit &ndash; ide&aacute;ln&iacute; třeba jako mal&yacute; d&aacute;rek nebo sběratelsk&yacute; kousek.</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<hr>\r\n<p>&nbsp;</p>\r\n<p class=\"p3\"><strong>Možnosti:</strong></p>\r\n<p class=\"p4\">&nbsp;</p>\r\n<p class=\"p1\">&bull; <span class=\"s2\"><strong>Sestaven&yacute; axolotl</strong></span> &ndash; připraven&yacute; k zavě&scaron;en&iacute;</p>\r\n<p class=\"p1\">&bull; <span class=\"s2\"><strong>Na kartě (DIY)</strong></span> &ndash; skvěl&aacute; z&aacute;bava při skl&aacute;d&aacute;n&iacute;</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<hr>\r\n<p>&nbsp;</p>\r\n<p class=\"p3\"><strong>Balen&iacute; obsahuje:</strong></p>\r\n<p class=\"p4\">&nbsp;</p>\r\n<p class=\"p1\">&bull; 1&times; axolotl (dle zvolen&eacute; varianty)</p>\r\n<p class=\"p1\">&bull; 1&times; kovov&yacute; kroužek na kl&iacute;če</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<hr>\r\n<p>&nbsp;</p>\r\n<p>&nbsp;</p>\r\n<p class=\"p1\">Perfektn&iacute; jako mal&yacute; d&aacute;rek pro děti, hr&aacute;če nebo kohokoliv, kdo miluje Minecraft nebo roztomil&eacute; věci. Vyti&scaron;těno s l&aacute;skou pomoc&iacute; 3D tisku&nbsp;</p>', '', 60.00, '#ffd700', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://drive.google.com/file/d/1YdD6ODL3d44gO_haMxqkMCrKr9Y0muQo/view?usp=sharing, https://drive.google.com/file/d/11Qy-_kbs7DlgX2-B7fh0PJ6fFs8LBuVy/view?usp=sharing', '2025-04-20 18:00:00', 'in_stock', 0, '2025-12-02 07:27:08', '{\"#ffd700\":0}', '{\"Možnost\": {\"Sestavená\": {\"price\": 5, \"stock\": 100}, \"Na kartě (DIY)\": {\"price\": 0, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 1, NULL, 0, NULL),
(92, 'Minecraft creeper', '', 'Skladem', NULL, '<p class=\"p1\">Hledáš milý a originální doplněk na klíče nebo batoh? Tento Creeper ti zaručeně udělá radost – a tentokrát nevybuchne! Vznikl pomocí 3D tisku a můžeš si vybrat, jestli ti přijde již sestavený nebo jako stavebnice na kartě, kde si ho jednoduše složíš sám.</p><p class=\"p1\"><br></p><p class=\"p2\"><span class=\"s1\"></span></p><hr><p></p><p class=\"p1\">\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n</p><p class=\"p3\"><b style=\"font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, \"Noto Sans\", \"Liberation Sans\", sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\", \"Noto Color Emoji\"; font-size: 1rem;\">Možnosti:</b></p><p class=\"p1\"><b><br></b></p><p class=\"p1\">• <b>Sestavený Creeper </b>– hotový a připravený k zavěšení</p><p class=\"p1\">\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n</p><p class=\"p1\">• <b>Na kartě (DIY)</b> – zábavné skládání<br>\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n</p><p class=\"p1\"><br></p><p class=\"p2\"><span class=\"s1\"></span></p><hr><p></p><p class=\"p1\">\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n</p><p class=\"p3\"><b style=\"font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, \"Noto Sans\", \"Liberation Sans\", sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\", \"Noto Color Emoji\"; font-size: 1rem;\">Balení obsahuje:</b></p><p class=\"p1\">• 1x Creeper (dle zvolené varianty)</p><p class=\"p1\">\r\n\r\n</p><p class=\"p1\">• 1x kovový kroužek na klíče<br>\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n</p><p class=\"p1\"><br></p><p class=\"p2\"><span class=\"s1\"></span></p><hr><p></p><p class=\"p1\">\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n\r\n</p><p class=\"p3\"><span style=\"font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, \"Noto Sans\", \"Liberation Sans\", sans-serif, \"Apple Color Emoji\", \"Segoe UI Emoji\", \"Segoe UI Symbol\", \"Noto Color Emoji\"; font-size: 1rem;\">Skvělý drobný dárek pro každého milovníka Minecraftu!</span></p>', '', 60.00, '#ffd700', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', NULL, '2025-04-20 18:00:00', 'in_stock', 0, '2025-12-02 07:26:57', '{\"#ffd700\":0}', '{\"Možnost\": {\"Sestavená\": {\"price\": 5, \"stock\": 100}, \"Na kartě (DIY)\": {\"price\": 0, \"stock\": 100}}}', NULL, 0, NULL, NULL, NULL, 1, NULL, 0, NULL),
(94, 'Váza- Vlna', '', 'Skladem', NULL, '<p class=\"p1\"><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\">V&aacute;za Vlna přin&aacute;&scaron;&iacute; do interi&eacute;ru harmonii pohybu. Jej&iacute; tvar inspirovan&yacute; mořsk&yacute;mi vlnami kr&aacute;sně lad&iacute; s přirozenost&iacute; rostlin.</span></p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Organick&yacute; vzhled</strong></span></span> &ndash; jemn&eacute; křivky připom&iacute;naj&iacute; pohyb vody a dod&aacute;vaj&iacute; květin&aacute;či eleganci.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Funkčn&iacute; design</span></strong></span> &ndash; stabiln&iacute; z&aacute;kladna a praktick&yacute; tvar pro snadn&eacute; přesazov&aacute;n&iacute;.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Ručně laděn&yacute; model</span></strong></span> &ndash; jedinečn&yacute; kousek z 3D tisku s l&aacute;skou k detailu.</li>\r\n<li><span style=\"font-weight: bolder;\"><span style=\"color: #e76363;\">Velikost</span></span>- na v&yacute;&scaron;ku 17 cm, na &scaron;&iacute;řku v největ&scaron;&iacute;m bodě 16 cm.</li>\r\n</ul>\r\n<p class=\"p3\">Ide&aacute;ln&iacute; pro ty, kdo hledaj&iacute; spojen&iacute; estetiky a př&iacute;rody.</p>', '', 349.00, 'Červená, Žlutá, Bílá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', NULL, '2025-05-19 15:00:00', 'in_stock', 0, '2025-12-02 07:21:07', '{\"Červená\":0,\"Žlutá\":0,\"Bílá\":0}', '[]', NULL, 0, NULL, NULL, NULL, 1, NULL, 0, NULL),
(95, 'Váza- Jemná vlnka', '', 'Skladem', NULL, '<p class=\"p1\"><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\">Jemn&aacute; Vlna je subtiln&iacute; a elegantn&iacute; v&aacute;za s hebce zvlněn&yacute;m povrchem. Perfektně dopln&iacute; jak modern&iacute;, tak př&iacute;rodn&iacute; interi&eacute;r.</span></p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Nadčasov&yacute; styl</strong></span></span> &ndash; decentn&iacute; vzor dod&aacute; prostoru jemnost a klid.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Univerz&aacute;ln&iacute; velikost</span></strong></span> &ndash; vhodn&yacute; pro &scaron;irokou &scaron;k&aacute;lu pokojov&yacute;ch rostlin.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Kvalitn&iacute; materi&aacute;l</span></strong></span> &ndash; vyroben&yacute; z odoln&eacute;ho PLA, kter&yacute; je &scaron;etrn&yacute; k př&iacute;rodě.</li>\r\n<li><strong><span style=\"color: #e76363;\">Velikost</span></strong>- na v&yacute;&scaron;ku 12cm, na &scaron;&iacute;řku v největ&scaron;&iacute;m bodě 17cm</li>\r\n</ul>\r\n<p class=\"p3\">Skvěl&aacute; volba pro milovn&iacute;ky jemn&eacute;ho designu a čist&yacute;ch lini&iacute;.</p>', '', 349.00, 'Červená, Bílá, Žlutá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', NULL, '2025-05-19 15:00:00', 'in_stock', 0, '2025-12-02 07:20:52', '{\"Červená\":0,\"Bílá\":0,\"Žlutá\":0}', '[]', NULL, 0, NULL, NULL, NULL, 1, NULL, 0, NULL),
(96, 'Váza- Elegantní Krystal', '', 'Skladem', NULL, '<p class=\"p1\"><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\">V&aacute;za Krystal zaujme sv&yacute;m v&yacute;razn&yacute;m geometrick&yacute;m vzhledem inspirovan&yacute;m strukturou drah&yacute;ch kamenů. Je jako &scaron;perk pro va&scaron;i rostlinu či su&scaron;enou květinu.</span></p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Origin&aacute;ln&iacute; design</strong></span></span> &ndash; ostr&eacute; linie a facety připom&iacute;naj&iacute;c&iacute; krystal dodaj&iacute; interi&eacute;ru jedinečn&yacute; charakter.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Dekorativn&iacute; i praktick&aacute;</span></strong></span> &ndash; skvěle se hod&iacute; na stůl, polici i parapet.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Lehk&aacute;, ale pevn&aacute;</span></strong></span> &ndash; vyti&scaron;těn&aacute; z kvalitn&iacute;ho PLA materi&aacute;lu, kter&yacute; je ekologick&yacute; a stabiln&iacute;.</li>\r\n<li><span style=\"font-weight: bolder;\"><span style=\"color: #e76363;\">Velikost</span></span>- na v&yacute;&scaron;ku 18 cm, průměr 6 cm.</li>\r\n</ul>\r\n<p class=\"p3\">Zv&yacute;razněte kr&aacute;su va&scaron;ich květin s elegantn&iacute; krystalovou v&aacute;zičkou.</p>', '', 349.00, 'Červená, Bílá, Černá, Žlutá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1DNbp4MBrOuV02PXHbysYKFZxVAD9h3_m,https://lh3.googleusercontent.com/d/1Rsw0U8CuhmE5NW1_32img-fKhvTlFJD0,https://lh3.googleusercontent.com/d/1XAiBKCbZ2t_ylVPWafTwnuUfY60-4AIx', '2025-05-19 15:00:00', 'in_stock', 0, '2025-12-02 07:20:06', '{\"Červená\":0,\"Bílá\":0,\"Černá\":0,\"Žlutá\":0}', '[]', NULL, 0, NULL, NULL, NULL, 0, NULL, 0, NULL),
(97, 'Váza- Slunečnice', '', 'Skladem', NULL, '<p class=\"p1\"><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\">V&aacute;za Slunečnice přin&aacute;&scaron;&iacute; teplo a radost do každ&eacute;ho kouta va&scaron;eho domova. Je inspirov&aacute;na tvarem květu slunečnice &ndash; symbolu optimismu a světla.</span></p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Hrav&yacute; květinov&yacute; motiv</strong></span></span> &ndash; připom&iacute;n&aacute; rozkvetlou slunečnici, ide&aacute;ln&iacute; pro rozjasněn&iacute; interi&eacute;ru.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">V&scaron;estrann&eacute; použit&iacute;</span></strong></span> &ndash; na su&scaron;en&eacute; květiny, drobn&eacute; rostliny nebo jako samostatn&aacute; dekorace.</li>\r\n<li><span class=\"s1\"><strong><span style=\"color: #e76363;\">Precizn&iacute; 3D tisk</span></strong></span> &ndash; detailn&iacute; zpracov&aacute;n&iacute; a kvalitn&iacute; proveden&iacute;.</li>\r\n<li><span style=\"font-weight: bolder;\"><span style=\"color: #e76363;\">Velikost</span></span>- na v&yacute;&scaron;ku 16,5 cm, průměr 4.5 cm.</li>\r\n</ul>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p class=\"p3\">Rozehraj svůj interi&eacute;r květem, kter&yacute; nikdy neuvadne.</p>', '', 349.00, 'Červená, Černá, Bílá, Žlutá, Modrá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1QaMuhU_uGr1Y8St026oXnLvF0IrIbieI,https://lh3.googleusercontent.com/d/1YiNRaN6sKwoz9xfCZCUqjUahZ0kSq4Y1', '2025-05-19 15:00:00', 'in_stock', 0, '2025-12-02 07:16:47', '{\"Červená\":0,\"Černá\":0,\"Bílá\":0,\"Žlutá\":0,\"Modrá\":0}', '[]', NULL, 0, 348.00, NULL, '2025-08-24 00:00:00', 0, NULL, 0, NULL),
(98, 'Lampa- Naomi', '', 'Skladem', NULL, '<p class=\"p1\"><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\">Nechte svůj interi&eacute;r rozz&aacute;řit jemn&yacute;m světlem lampy <strong>Naomi</strong> &ndash; spojen&iacute; elegance, klidu a modern&iacute;ho designu.</span></p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><span style=\"color: #e76363;\"><span class=\"s1\"><strong>Organick&eacute; linie</strong></span></span> &ndash; tvar lampy inspirovan&yacute; př&iacute;rodou vytv&aacute;ř&iacute; harmonii v každ&eacute;m prostoru.</li>\r\n<li><span class=\"s1\" style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\"><strong><span style=\"color: #e76363;\">Měkk&eacute; a tepl&eacute; světlo</span></strong></span><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\"> &ndash; ide&aacute;ln&iacute; pro večern&iacute; relaxaci nebo z&uacute;tulněn&iacute; pracovn&iacute;ho koutu.</span></li>\r\n<li><span class=\"s1\" style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\"><strong><span style=\"color: #e76363;\">Jedinečn&yacute; 3D tisk</span></strong></span><span style=\"font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, \'Helvetica Neue\', Arial, \'Noto Sans\', \'Liberation Sans\', sans-serif, \'Apple Color Emoji\', \'Segoe UI Emoji\', \'Segoe UI Symbol\', \'Noto Color Emoji\'; font-size: 1rem;\"> &ndash; každ&yacute; kus je origin&aacute;l vyroben&yacute; s důrazem na detail.</span></li>\r\n</ul>\r\n<p class=\"p3\">Naomi &ndash; minimalistick&yacute; &scaron;perk mezi lampami, kter&yacute; potě&scaron;&iacute; oko i du&scaron;i.</p>', '<div class=\"product-details-container\">\r\n<h2 class=\"h4 mb-3\">&nbsp;</h2>\r\n<table class=\"table table-bordered table-striped table-sm mb-3\">\r\n<tbody>\r\n<tr>\r\n<th style=\"width: 260px;\">Model</th>\r\n<td>Naomi</td>\r\n</tr>\r\n<tr>\r\n<th>Napět&iacute;</th>\r\n<td>230 V ~ 50 Hz</td>\r\n</tr>\r\n<tr>\r\n<th>Patice ž&aacute;rovky</th>\r\n<td>E14</td>\r\n</tr>\r\n<tr>\r\n<th>Povolen&yacute; světeln&yacute; zdroj</th>\r\n<td>V&Yacute;HRADNĚ LED ž&aacute;rovka</td>\r\n</tr>\r\n<tr>\r\n<th>Maxim&aacute;ln&iacute; př&iacute;kon zdroje</th>\r\n<td>5 W (LED)</td>\r\n</tr>\r\n<tr>\r\n<th>Tř&iacute;da ochrany</th>\r\n<td>II (dvojit&aacute; izolace)</td>\r\n</tr>\r\n<tr>\r\n<th>Nap&aacute;jec&iacute; kabel</th>\r\n<td>Pevně připojen&yacute;, d&eacute;lka 3 m</td>\r\n</tr>\r\n<tr>\r\n<th>Prostřed&iacute;</th>\r\n<td>Pouze pro vnitřn&iacute;, such&eacute; použit&iacute; (IP20)</td>\r\n</tr>\r\n<tr>\r\n<th>Certifikace a shoda</th>\r\n<td>CE, splňuje př&iacute;slu&scaron;n&eacute; normy</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<div class=\"alert alert-warning mb-4\" role=\"alert\"><strong>Důležit&eacute; bezpečnostn&iacute; upozorněn&iacute;:</strong> Pro bezpečn&yacute; a dlouholet&yacute; provoz lampy použ&iacute;vejte <strong>V&Yacute;HRADNĚ LED ž&aacute;rovku</strong> s patic&iacute; E14 a maxim&aacute;ln&iacute;m př&iacute;konem <strong>5 W</strong>. Použit&iacute; klasick&eacute; nebo halogenov&eacute; ž&aacute;rovky může způsobit nevratn&eacute; po&scaron;kozen&iacute; lampy a představuje riziko pož&aacute;ru.</div>\r\n<!-- Panel s QR kódem a informacemi -->\r\n<section class=\"info-panel\" aria-labelledby=\"info-panel-title\">\r\n<h3 id=\"info-panel-title\" class=\"info-title\">V&scaron;e důležit&eacute; na jednom m&iacute;stě</h3>\r\n<p class=\"mb-2\">Společně s lampou obdrž&iacute;te unik&aacute;tn&iacute; 3D ti&scaron;těn&yacute; QR k&oacute;d. Po jeho naskenov&aacute;n&iacute; z&iacute;sk&aacute;te okamžit&yacute; př&iacute;stup ke v&scaron;em potřebn&yacute;m informac&iacute;m:</p>\r\n<ul class=\"info-list\">\r\n<li>Kompletn&iacute; n&aacute;vod k použit&iacute; a bezpečnostn&iacute; pokyny</li>\r\n<li>ES Prohl&aacute;&scaron;en&iacute; o shodě (certifikace CE)</li>\r\n<li>Informace o va&scaron;&iacute; objedn&aacute;vce</li>\r\n</ul>\r\n<p class=\"info-note\">Před prvn&iacute;m použit&iacute;m lampy si pros&iacute;m tyto dokumenty pečlivě pročtěte.</p>\r\n</section>\r\n</div>', 549.00, 'Červená, Žlutá, Bílá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1PMGFoLfR6_WhCaV89ckk2iSH4CmI02oM,https://lh3.googleusercontent.com/d/1RNOCuheeKGndxpN9-iCOPtqxwjLkH619,https://lh3.googleusercontent.com/d/1cOCHljS4HlRBMjdkgBxp5PJpI6FZtlSv,https://lh3.googleusercontent.com/d/1XUrRqPKVeTeUcI8-UKWp72I7Dqf50GW-', '2025-06-01 15:00:00', 'in_stock', 0, '2026-01-04 23:14:17', '{\"Červená\":0,\"Žlutá\":0,\"Bílá\":0}', '[]', NULL, 1, 495.00, '2026-01-05 00:15:00', '2026-01-10 00:00:00', 0, NULL, 0, 'models/98.glb'),
(100, 'Lampa- Shroom', '', 'Skladem', NULL, '<p class=\"lead\">Shroom je jemn&aacute;, př&iacute;rodou inspirovan&aacute; lampa, kter&aacute; vytv&aacute;ř&iacute; &uacute;tuln&eacute; ambientn&iacute; světlo a funguje jako v&yacute;razn&yacute;, ale nen&aacute;padn&yacute; akcent v interi&eacute;ru. Minimalistick&yacute; tvar &bdquo;houby&ldquo; a pravideln&eacute; žebrov&aacute;n&iacute; rozptyluj&iacute; světlo do př&iacute;jemn&eacute;ho, tepl&eacute;ho z&aacute;voje.</p>\r\n<ul class=\"features\">\r\n<li><strong>Nadčasov&yacute; tvar:</strong> organick&aacute; silueta, kter&aacute; zapadne do modern&iacute;ch i klasick&yacute;ch interi&eacute;rů.</li>\r\n<li><strong>Měkk&eacute; ambientn&iacute; světlo:</strong> ide&aacute;ln&iacute; k odpočinku, večern&iacute;mu čten&iacute; nebo jako n&aacute;ladov&eacute; osvětlen&iacute;.</li>\r\n<li><strong>Detailn&iacute; zpracov&aacute;n&iacute;:</strong> čist&eacute; linie a struktura st&iacute;nidla, kter&aacute; d&aacute;v&aacute; světlu charakter.</li>\r\n<li><strong>Každ&yacute; kus jedinečn&yacute;:</strong> pečlivě vyrobeno v mal&yacute;ch s&eacute;ri&iacute;ch.</li>\r\n</ul>\r\n<div class=\"availability\" style=\"margin-top: 1rem;\">\r\n<p>&nbsp;</p>\r\n</div>\r\n<div class=\"notes\" style=\"margin-top: 1rem; font-size: 0.95rem;\">\r\n<p><em>Pozn&aacute;mka:</em> Barevn&eacute; odst&iacute;ny se mohou na různ&yacute;ch obrazovk&aacute;ch m&iacute;rně li&scaron;it. Doporučujeme použ&iacute;vat kvalitn&iacute; LED ž&aacute;rovky s teplou barvou světla pro nejhezč&iacute; atmosf&eacute;ru.</p>\r\n</div>\r\n<div class=\"cta\" style=\"margin-top: 1rem;\">\r\n<p>&nbsp;</p>\r\n</div>', '<div class=\"product-details-container\">\r\n<h2 class=\"h4 mb-3\">&nbsp;</h2>\r\n<table class=\"table table-bordered table-striped table-sm mb-3\">\r\n<tbody>\r\n<tr>\r\n<th style=\"width: 260px;\">Model</th>\r\n<td>SHROOM</td>\r\n</tr>\r\n<tr>\r\n<th>Napět&iacute;</th>\r\n<td>230 V ~ 50 Hz</td>\r\n</tr>\r\n<tr>\r\n<th>Patice ž&aacute;rovky</th>\r\n<td>E14</td>\r\n</tr>\r\n<tr>\r\n<th>Povolen&yacute; světeln&yacute; zdroj</th>\r\n<td>V&Yacute;HRADNĚ LED ž&aacute;rovka</td>\r\n</tr>\r\n<tr>\r\n<th>Maxim&aacute;ln&iacute; př&iacute;kon zdroje</th>\r\n<td>5 W (LED)</td>\r\n</tr>\r\n<tr>\r\n<th>Tř&iacute;da ochrany</th>\r\n<td>II (dvojit&aacute; izolace)</td>\r\n</tr>\r\n<tr>\r\n<th>Nap&aacute;jec&iacute; kabel</th>\r\n<td>Pevně připojen&yacute;, d&eacute;lka 3 m</td>\r\n</tr>\r\n<tr>\r\n<th>Prostřed&iacute;</th>\r\n<td>Pouze pro vnitřn&iacute;, such&eacute; použit&iacute; (IP20)</td>\r\n</tr>\r\n<tr>\r\n<th>Certifikace a shoda</th>\r\n<td>CE, splňuje př&iacute;slu&scaron;n&eacute; normy</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<div class=\"alert alert-warning mb-4\" role=\"alert\"><strong>Důležit&eacute; bezpečnostn&iacute; upozorněn&iacute;:</strong> Pro bezpečn&yacute; a dlouholet&yacute; provoz lampy použ&iacute;vejte <strong>V&Yacute;HRADNĚ LED ž&aacute;rovku</strong> s patic&iacute; E14 a maxim&aacute;ln&iacute;m př&iacute;konem <strong>5 W</strong>. Použit&iacute; klasick&eacute; nebo halogenov&eacute; ž&aacute;rovky může způsobit nevratn&eacute; po&scaron;kozen&iacute; lampy a představuje riziko pož&aacute;ru.</div>\r\n<!-- Panel s QR kódem a informacemi -->\r\n<section class=\"info-panel\" aria-labelledby=\"info-panel-title\">\r\n<h3 id=\"info-panel-title\" class=\"info-title\">V&scaron;e důležit&eacute; na jednom m&iacute;stě</h3>\r\n<p class=\"mb-2\">Společně s lampou obdrž&iacute;te unik&aacute;tn&iacute; 3D ti&scaron;těn&yacute; QR k&oacute;d. Po jeho naskenov&aacute;n&iacute; z&iacute;sk&aacute;te okamžit&yacute; př&iacute;stup ke v&scaron;em potřebn&yacute;m informac&iacute;m:</p>\r\n<ul class=\"info-list\">\r\n<li>Kompletn&iacute; n&aacute;vod k použit&iacute; a bezpečnostn&iacute; pokyny</li>\r\n<li>ES Prohl&aacute;&scaron;en&iacute; o shodě (certifikace CE)</li>\r\n<li>Informace o va&scaron;&iacute; objedn&aacute;vce</li>\r\n</ul>\r\n<p class=\"info-note\">Před prvn&iacute;m použit&iacute;m lampy si pros&iacute;m tyto dokumenty pečlivě pročtěte.</p>\r\n</section>\r\n</div>', 649.00, 'Žlutá, Oranžová', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1E8JlrAizaRqho2E0tMRBuyhu55J1YQqL,https://lh3.googleusercontent.com/d/11VhEsSmF_idWVtnXThI0iRPxkVYlvJRj,https://lh3.googleusercontent.com/d/1RGS5PDyRF22nwW_drb5vwTOWv2BErsBX,https://lh3.googleusercontent.com/d/1yrD-Q_y8kfKWTc6aD8HZTb6hi_7yGn9e,https://lh3.googleusercontent.com/d/1-fAbka8YT4SamEkLVQirjtYsnzOEQt4j,https://lh3.googleusercontent.com/d/1sQyhL2s-Zn8IctzlMQRzChKRuL95eM7A', '2025-09-01 15:00:00', 'in_stock', 0, '2025-12-01 08:07:17', '{\"Žlutá\":0}', '[]', '2025-09-01', 0, 510.00, NULL, '2025-09-01 00:00:00', 0, NULL, 0, NULL);
INSERT INTO `product` (`id`, `name`, `model`, `availability`, `slug`, `description`, `html_tech_specs`, `price`, `colors`, `unavailable_colors`, `no_color_required`, `color_components`, `component_images`, `image_url`, `available_from`, `stock_status`, `is_preorder`, `updated_at`, `color_prices`, `variants`, `release_date`, `sale_enabled`, `sale_price`, `sale_start`, `sale_end`, `is_hidden`, `baleni`, `is_lamp_config`, `model_3d_path`) VALUES
(101, 'Lampa- Ripple', NULL, 'Skladem', NULL, '<p data-start=\"236\" data-end=\"720\">Minimalistick&aacute; lampa <strong data-start=\"264\" data-end=\"274\">Ripple</strong> v sobě spojuje jemnost a rytmus. Jej&iacute; hladce vlnit&yacute; tvar připom&iacute;n&aacute; kruhy na vodn&iacute; hladině, kter&eacute; se &scaron;&iacute;ř&iacute; ti&scaron;e a pomalu &ndash; stejně jako jej&iacute; tepl&eacute;, rovnoměrn&eacute; světlo.<br data-start=\"438\" data-end=\"441\">Každ&yacute; kus je <strong data-start=\"454\" data-end=\"500\">precizně vyti&scaron;těn z ekologick&eacute;ho materi&aacute;lu</strong> a navržen tak, aby přinesl do interi&eacute;ru <strong data-start=\"541\" data-end=\"587\">pocit klidu, harmonie a vyv&aacute;žen&eacute;ho designu</strong>.<br data-start=\"588\" data-end=\"591\">D&iacute;ky kontrastu matn&eacute;ho b&iacute;l&eacute;ho difuzoru a v&yacute;razn&eacute; barevn&eacute; z&aacute;kladny se Ripple hod&iacute; do <strong data-start=\"675\" data-end=\"717\">modern&iacute;ch i př&iacute;rodně laděn&yacute;ch prostorů</strong>.</p>\r\n<p>&nbsp;</p>\r\n<p data-start=\"722\" data-end=\"788\"><em data-start=\"726\" data-end=\"786\">Nechte světlo plynout. Nechte Ripple proměnit v&aacute;&scaron; prostor.</em></p>', '<div class=\"product-details-container\">\n<h2 class=\"h4 mb-3\">&nbsp;</h2>\n<table class=\"table table-bordered table-striped table-sm mb-3\">\n<tbody>\n<tr>\n<th style=\"width: 260px;\">Model</th>\n<td>Ripple</td>\n</tr>\n<tr>\n<th>Napět&iacute;</th>\n<td>230 V ~ 50 Hz</td>\n</tr>\n<tr>\n<th>Patice ž&aacute;rovky</th>\n<td>E14</td>\n</tr>\n<tr>\n<th>Povolen&yacute; světeln&yacute; zdroj</th>\n<td>V&Yacute;HRADNĚ LED ž&aacute;rovka</td>\n</tr>\n<tr>\n<th>Maxim&aacute;ln&iacute; př&iacute;kon zdroje</th>\n<td>5 W (LED)</td>\n</tr>\n<tr>\n<th>Tř&iacute;da ochrany</th>\n<td>II (dvojit&aacute; izolace)</td>\n</tr>\n<tr>\n<th>Nap&aacute;jec&iacute; kabel</th>\n<td>Pevně připojen&yacute;, d&eacute;lka 3 m</td>\n</tr>\n<tr>\n<th>Prostřed&iacute;</th>\n<td>Pouze pro vnitřn&iacute;, such&eacute; použit&iacute; (IP20)</td>\n</tr>\n<tr>\n<th>Certifikace a shoda</th>\n<td>CE, splňuje př&iacute;slu&scaron;n&eacute; normy</td>\n</tr>\n</tbody>\n</table>\n<div class=\"alert alert-warning mb-4\" role=\"alert\"><strong>Důležit&eacute; bezpečnostn&iacute; upozorněn&iacute;:</strong> Pro bezpečn&yacute; a dlouholet&yacute; provoz lampy použ&iacute;vejte <strong>V&Yacute;HRADNĚ LED ž&aacute;rovku</strong> s patic&iacute; E14 a maxim&aacute;ln&iacute;m př&iacute;konem <strong>5 W</strong>. Použit&iacute; klasick&eacute; nebo halogenov&eacute; ž&aacute;rovky může způsobit nevratn&eacute; po&scaron;kozen&iacute; lampy a představuje riziko pož&aacute;ru.</div>\n<!-- Panel s QR kódem a informacemi -->\n<section class=\"info-panel\" aria-labelledby=\"info-panel-title\">\n<h3 id=\"info-panel-title\" class=\"info-title\">V&scaron;e důležit&eacute; na jednom m&iacute;stě</h3>\n<p class=\"mb-2\">Společně s lampou obdrž&iacute;te unik&aacute;tn&iacute; 3D ti&scaron;těn&yacute; QR k&oacute;d. Po jeho naskenov&aacute;n&iacute; z&iacute;sk&aacute;te okamžit&yacute; př&iacute;stup ke v&scaron;em potřebn&yacute;m informac&iacute;m:</p>\n<ul class=\"info-list\">\n<li>Kompletn&iacute; n&aacute;vod k použit&iacute; a bezpečnostn&iacute; pokyny</li>\n<li>ES Prohl&aacute;&scaron;en&iacute; o shodě (certifikace CE)</li>\n<li>Informace o va&scaron;&iacute; objedn&aacute;vce</li>\n</ul>\n<p class=\"info-note\">Před prvn&iacute;m použit&iacute;m lampy si pros&iacute;m tyto dokumenty pečlivě pročtěte.</p>\n</section>\n</div>', 600.00, 'Černá, Žlutá, Červená, Oranžová', NULL, 0, NULL, NULL, '', '2025-10-26 23:37:00', 'in_stock', 0, '2025-11-28 08:25:59', '{\"Černá\":0,\"Žlutá\":0,\"Červená\":0,\"Oranžová\":0}', '[]', NULL, 0, NULL, NULL, NULL, 1, '', 0, NULL),
(102, 'Lampa- WAVEA', '', 'Skladem', NULL, '<p><strong>Wavea</strong> je stoln&iacute; lampa inspirovan&aacute; jemn&yacute;m pohybem vln &mdash; tvarovan&eacute; linie vytv&aacute;řej&iacute; měkk&eacute; rozpt&yacute;len&eacute; světlo, kter&eacute; v m&iacute;stnosti navod&iacute; klidnou atmosf&eacute;ru. Design kombinuje organick&yacute; tvar s modern&iacute;m zpracov&aacute;n&iacute;m vhodn&yacute;m do minimalistick&yacute;ch i &uacute;tuln&yacute;ch interi&eacute;rů.</p>\r\n<ul>\r\n<li><strong>Jemn&eacute;, rozpt&yacute;len&eacute; světlo</strong> &ndash; vlnovit&aacute; struktura st&iacute;nidla rozptyluje světlo rovnoměrně a vytv&aacute;ř&iacute; teplou, př&iacute;jemnou atmosf&eacute;ru.</li>\r\n<li><strong>Transparentn&iacute; vr&scaron;ky</strong> &ndash; v&scaron;echny vr&scaron;ky/st&iacute;nidla jsou z průsvitn&eacute;ho materi&aacute;lu, kter&yacute; nech&aacute;v&aacute; světlo jemně prosv&iacute;tat a zv&yacute;razn&iacute; plastičnost tvaru.</li>\r\n<li><strong>3D tisk</strong> &ndash; každ&yacute; kus je vyroben technikou 3D tisku s důrazem na detail a kvalitn&iacute; povrchovou &uacute;pravu.</li>\r\n<li><strong>Modul&aacute;rn&iacute; možnosti</strong> &ndash; voliteln&eacute; barvy a povrchy st&iacute;nidel pro snadn&eacute; přizpůsoben&iacute; stylu interi&eacute;ru.</li>\r\n</ul>\r\n<p><em>Wavea</em> je ide&aacute;ln&iacute; volbou, pokud hled&aacute;te lampu, kter&aacute; dod&aacute; prostoru klidn&yacute;, přirozen&yacute; n&aacute;dech bez zbytečn&eacute;ho křiku. Vytvořili jsme ji tak, aby fungovala jako diskr&eacute;tn&iacute;, ale charakteristick&yacute; prvek každ&eacute;ho interi&eacute;ru.</p>', '<div class=\"product-details-container\">\n<h2 class=\"h4 mb-3\">&nbsp;</h2>\n<table class=\"table table-bordered table-striped table-sm mb-3\">\n<tbody>\n<tr>\n<th style=\"width: 260px;\">Model</th>\n<td>WAVEA</td>\n</tr>\n<tr>\n<th>Napět&iacute;</th>\n<td>230 V ~ 50 Hz</td>\n</tr>\n<tr>\n<th>Patice ž&aacute;rovky</th>\n<td>E14</td>\n</tr>\n<tr>\n<th>Povolen&yacute; světeln&yacute; zdroj</th>\n<td>V&Yacute;HRADNĚ LED ž&aacute;rovka</td>\n</tr>\n<tr>\n<th>Maxim&aacute;ln&iacute; př&iacute;kon zdroje</th>\n<td>5 W (LED)</td>\n</tr>\n<tr>\n<th>Tř&iacute;da ochrany</th>\n<td>II (dvojit&aacute; izolace)</td>\n</tr>\n<tr>\n<th>Nap&aacute;jec&iacute; kabel</th>\n<td>Pevně připojen&yacute;, d&eacute;lka 3 m</td>\n</tr>\n<tr>\n<th>Prostřed&iacute;</th>\n<td>Pouze pro vnitřn&iacute;, such&eacute; použit&iacute; (IP20)</td>\n</tr>\n<tr>\n<th>Certifikace a shoda</th>\n<td>CE, splňuje př&iacute;slu&scaron;n&eacute; normy</td>\n</tr>\n</tbody>\n</table>\n<div class=\"alert alert-warning mb-4\" role=\"alert\"><strong>Důležit&eacute; bezpečnostn&iacute; upozorněn&iacute;:</strong> Pro bezpečn&yacute; a dlouholet&yacute; provoz lampy použ&iacute;vejte <strong>V&Yacute;HRADNĚ LED ž&aacute;rovku</strong> s patic&iacute; E14 a maxim&aacute;ln&iacute;m př&iacute;konem <strong>5 W</strong>. Použit&iacute; klasick&eacute; nebo halogenov&eacute; ž&aacute;rovky může způsobit nevratn&eacute; po&scaron;kozen&iacute; lampy a představuje riziko pož&aacute;ru.</div>\n<!-- Panel s QR kódem a informacemi -->\n<section class=\"info-panel\" aria-labelledby=\"info-panel-title\">\n<h3 id=\"info-panel-title\" class=\"info-title\">V&scaron;e důležit&eacute; na jednom m&iacute;stě</h3>\n<p class=\"mb-2\">Společně s lampou obdrž&iacute;te unik&aacute;tn&iacute; 3D ti&scaron;těn&yacute; QR k&oacute;d. Po jeho naskenov&aacute;n&iacute; z&iacute;sk&aacute;te okamžit&yacute; př&iacute;stup ke v&scaron;em potřebn&yacute;m informac&iacute;m:</p>\n<ul class=\"info-list\">\n<li>Kompletn&iacute; n&aacute;vod k použit&iacute; a bezpečnostn&iacute; pokyny</li>\n<li>ES Prohl&aacute;&scaron;en&iacute; o shodě (certifikace CE)</li>\n<li>Informace o va&scaron;&iacute; objedn&aacute;vce</li>\n</ul>\n<p class=\"info-note\">Před prvn&iacute;m použit&iacute;m lampy si pros&iacute;m tyto dokumenty pečlivě pročtěte.</p>\n</section>\n</div>', 649.00, NULL, NULL, 0, '[{\"name\":\"Horní část\",\"colors\":[\"Žlutá\",\"Bílá\",\"Modrá\",\"Zelená\",\"Fialová\",\"Červená\"],\"required\":true},{\"name\":\"Spodní část\",\"colors\":[\"Žlutá\",\"Bílá\",\"Modrá\",\"Zelená\",\"Červená\",\"Černá\"],\"required\":true}]', '{\"nozicky\":[{\"image\":\"uploads\\/products\\/components\\/693160071bef0_691a4e270d1fa_Návrh bez názvu (4).png\",\"name\":\"Červená\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318488f39f4_691a4e270d2b0_Návrh bez názvu (2).png\",\"name\":\"Zelená\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318488f3ab5_691a4e270d33c_Návrh bez názvu.png\",\"name\":\"Bílá\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318488f3b4d_691a29aee739e_Návrh bez názvu (1).png\",\"name\":\"Modrá\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318488f3bd8_691a46f970f97_Návrh bez názvu (3).png\",\"name\":\"Žlutá\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318488f3c60_691a6202441af_daw (2).png\",\"name\":\"Černá\",\"colors\":[]}],\"vrsek\":[{\"image\":\"uploads\\/products\\/components\\/693160071c060_691a4f64da69e_Návrh bez názvu.png\",\"name\":\"Červená\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318341a1301_691a4f64da433_Návrh bez názvu (3).png\",\"name\":\"Modrá\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318341a13c0_691a29aee74b3_Návrh bez názvu (10).png\",\"name\":\"Zelená\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318341a144c_691a4f64da5e8_Návrh bez názvu (1).png\",\"name\":\"Žlutá\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318341a14d4_691a4f64da52c_Návrh bez názvu (2).png\",\"name\":\"Bílá\",\"colors\":[]},{\"image\":\"uploads\\/products\\/components\\/69318341a155c_691a62160a6dd_daw (1).png\",\"name\":\"Fialová\",\"colors\":[]}]}', 'https://lh3.googleusercontent.com/d/1Y-HKSil15zfILBhHbLr6ptbYxve2p4Y_,https://lh3.googleusercontent.com/d/1Y9qjkc6J_9ntGzBcva1vPB3_m7Lq09ld,https://lh3.googleusercontent.com/d/1YMP7wFRlICIg_k66Hg3rlihkfY5Wj4F4,https://lh3.googleusercontent.com/d/11JOc5BUB0s1eHW79RPSiQvh9WrWBt9_W,https://lh3.googleusercontent.com/d/12Y41e-Xs64Kwzb6oaXaGJ8NFaNaPpV4g', '2025-11-05 15:00:00', 'in_stock', 0, '2026-01-14 09:45:35', NULL, '[]', NULL, 1, 551.65, '2026-01-05 00:15:00', '2026-01-10 00:00:00', 0, '', 1, NULL),
(103, 'Accessory k AirPods Max', NULL, 'Skladem', NULL, '<p><strong>Stylov&yacute; doplněk k AirPods Max</strong>, kter&yacute; dod&aacute; va&scaron;im sluch&aacute;tkům jedinečn&yacute; vzhled a elegantn&iacute; detail. Navrženo a vyrobeno v&nbsp;Česku s&nbsp;důrazem na preciznost a design.</p>\r\n<p><strong>Dostupn&eacute; ve dvou materi&aacute;lech:</strong></p>\r\n<ul>\r\n<li><strong>PLA</strong> &ndash; matněj&scaron;&iacute; vzhled, př&iacute;jemn&yacute; na dotek, vhodn&yacute; pro běžn&eacute; použ&iacute;v&aacute;n&iacute; a interi&eacute;r.</li>\r\n<li><strong>PETG</strong> &ndash; odolněj&scaron;&iacute; a pružněj&scaron;&iacute; varianta, ide&aacute;ln&iacute; pro každodenn&iacute; no&scaron;en&iacute; a vy&scaron;&scaron;&iacute; z&aacute;těž.</li>\r\n</ul>\r\n<p>Lehk&yacute;, pečlivě ti&scaron;těn&yacute; doplněk, kter&yacute; perfektně sed&iacute; a nenaru&scaron;uje origin&aacute;ln&iacute; funkce sluch&aacute;tek.</p>\r\n<p><em>Produkt se prod&aacute;v&aacute; po dvou kusech.</em></p>', '', 549.00, 'Černá, Modrá, Oranžová, Žlutá, Bílá', NULL, 0, NULL, NULL, '', '2025-11-05 09:35:00', 'in_stock', 0, '2025-11-28 08:26:07', '{\"Oranžová\":0,\"Bílá\":0,\"Černá\":0,\"Žlutá\":0,\"Modrá\":0,\"Kaštanová červená\":0}', '{\"Materiál\": {\"PLA\": {\"price\": 0, \"stock\": 100}, \"PETG\": {\"price\": 0, \"stock\": 100, \"colors\": [\"Modrá\", \"Červená\", \"Žlutá\", \"Bílá\", \"Zelená\", \"Šedivá\"]}}}', NULL, 0, NULL, NULL, NULL, 1, '', 0, NULL),
(105, 'Spiral Waeva', '', 'Skladem', NULL, '<div class=\"product-card\">\n<p class=\"lead\">Ručn&iacute; 3D tisk, modern&iacute; tvar pleten&eacute; spir&aacute;ly &mdash; mal&yacute;, stylov&yacute; doplněk na v&aacute;nočn&iacute; stromek nebo jako d&aacute;rek.</p>\n<ul>\n<li><strong>Materi&aacute;l:</strong> PLA (3D tiskov&yacute; filament)</li>\n<li><strong>Rozměr na v&yacute;&scaron;ku:</strong> 26cm</li>\n<li><strong>Varianty prodeje:</strong> 1 ks / set 3 ks / set 6 ks</li>\n</ul>\n<p>Každ&aacute; ozdoba je vyti&scaron;těn&aacute; a kontrolovan&aacute; ručně. Barvy ve skutečnosti se mohou m&iacute;rně li&scaron;it od fotografi&iacute;.</p>\n<h3>Proč Spiral Weave?</h3>\n<p>Minimalistick&yacute;, ale v&yacute;razn&yacute; design inspirovan&yacute; rostlinn&yacute;mi vzory a texturou tkaniny. Lehk&eacute;, odoln&eacute; a skvěle vypadaj&iacute; v různ&yacute;ch barevn&yacute;ch kombinac&iacute;ch &mdash; ide&aacute;ln&iacute; jako mal&yacute; designov&yacute; d&aacute;rek.<br><br>K každ&eacute; jedn&eacute; <strong>Spiwal Wavea&nbsp;</strong>dostanete zdarma h&aacute;ček na zavě&scaron;en&iacute;.</p>\n</div>', '', 79.00, 'Žlutá, Zelená, Modrá, Fialová, Bílá, Oranžová, Béžová, Červená', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1PYZI3vw4Fsk6nWV7A_dww40Ohpf_rLzu,https://lh3.googleusercontent.com/d/1Elcz1BtgE8utVAzLz5QQ0U4ftS6hC6ri', '2025-11-20 13:56:00', 'in_stock', 0, '2026-01-05 20:27:24', '{\"Žlutá\":0,\"Zelená\":0,\"Modrá\":0,\"Fialová\":0,\"Bílá\":0,\"Oranžová\":0,\"Béžová\":0}', '{\"Počet kusů\": {\"1ks\": {\"price\": 0, \"stock\": 100}, \"3ks\": {\"price\": 140, \"stock\": 100}, \"6ks\": {\"price\": 320, \"stock\": 100}}}', NULL, 1, 67.00, '2026-01-05 00:10:00', '2026-01-10 00:00:00', 0, '', 1, 'models/105.glb'),
(106, 'Lampa- FORMA', '', 'Skladem', NULL, '', '', 650.00, '4', NULL, 0, NULL, '{\"nozicky\":[{\"image\":\"https:\\/\\/drive.google.com\\/file\\/d\\/1lmzR6PSO8I3_BhVDUWG_hnq7GX3uIijM\\/view?usp=sharing\",\"name\":\"Bílá\",\"colors\":[]}],\"vrsek\":[]}', '', '2025-11-27 09:53:00', 'in_stock', 0, '2025-11-28 08:26:13', '{\"4\":0}', '[]', NULL, 0, NULL, NULL, NULL, 1, '', 1, NULL),
(107, 'TEST', '', 'Skladem', NULL, '', '', 1.00, NULL, NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', NULL, '2025-12-08 10:01:00', 'in_stock', 0, '2025-12-08 09:01:30', '[]', '[]', NULL, 0, NULL, NULL, NULL, 1, '', 0, NULL),
(108, 'Lampa- Penis', '', 'Skladem', NULL, '<p>\nVýrazný 3D tištěný kousek, který rozhodně <strong>nezůstane bez povšimnutí</strong>.\nDesign, který si na nic nehraje a říká věci tak, jak jsou.  \nIdeální jako odvážný doplněk, dárek s humorem nebo prostě jen proto,\nže můžeš.\n</p>\n\n<ul>\n  <li><strong>Výška:</strong> cca 19 cm</li>\n  <li><strong>Průměr:</strong> cca 6–7 cm</li>\n  <li><strong>Povrch:</strong> jemně vrstvený, matný</li>\n</ul>\n\n\n\n<p>\n<strong>Upozornění:</strong> Tento produkt může vyvolat smích,\nzvednuté obočí nebo nečekané otázky návštěv.\nPoužívej s rozvahou… nebo vůbec ne.\n</p>\n\n\n\n<p><strong>Doručení pouze po ČR a SK</strong></p>\n', '', 550.00, 'Černá, Modrá, Zelená, Ohnivě červená, Růžová, Žlutá, Bílá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/1irmHpsrDTwE7aNfwWFWa5M5Xa1ptwAxz,https://lh3.googleusercontent.com/d/1Sk3uAGAZdUnOhrdqz1GtEGN0f2Et_4JN,https://lh3.googleusercontent.com/d/12ugBwz6DaOc6gU3kqWd8WeXXg9ywcsvD', '2025-12-08 17:06:00', 'in_stock', 0, '2026-01-05 20:29:31', '{\"Zelená\":0,\"Modrá\":0,\"Žlutá\":0,\"Černá\":0,\"Bílá\":0,\"Ohnivě červená\":0}', '{\"Žárovka\": {\"S žárovkou\": {\"price\": 70, \"stock\": 100}, \"Bez žárovky\": {\"price\": 0, \"stock\": 100}}}', '2026-01-03', 1, 400.00, NULL, '2026-01-10 00:00:00', 1, '', 0, NULL),
(109, 'Dildo', '', 'Skladem', NULL, '<div class=\"product-description\">\r\n<p><strong>Designov&eacute; dildo &ndash; průměr 5 cm, v&yacute;&scaron;ka 19 cm</strong><br>Minimalistick&yacute; doplněk, kter&yacute; spojuje čist&yacute; design, jednoduchost a funkčnost. S průměrem cca <strong>5 cm</strong> a v&yacute;&scaron;kou <strong>19 cm</strong> nab&iacute;z&iacute; komfortn&iacute; velikost s v&yacute;raznou př&iacute;tomnost&iacute;. D&iacute;ky elegantn&iacute;m lini&iacute;m a nen&aacute;padn&eacute;mu vzhledu působ&iacute; stylově i mimo použit&iacute;.</p>\r\n<p>Produkt je vyroben z materi&aacute;lu <strong>TPU 90</strong>. Nejedn&aacute; se o 100% silikon. Materi&aacute;l je pružn&yacute;, odoln&yacute; a vhodn&yacute; pro tento typ v&yacute;robku.</p>\r\n<h3>Instrukce a důležit&eacute; informace</h3>\r\n<table style=\"border-collapse: collapse; width: 100%;\" border=\"1\" cellspacing=\"0\" cellpadding=\"8\">\r\n<tbody>\r\n<tr>\r\n<td><strong>Materi&aacute;l</strong></td>\r\n<td>TPU 90 (nejedn&aacute; se o 100% silikon)</td>\r\n</tr>\r\n<tr>\r\n<td><strong>Použit&iacute;</strong></td>\r\n<td>Doporučeno použ&iacute;vat v&yacute;hradně s kondomem.</td>\r\n</tr>\r\n<tr>\r\n<td><strong>Hygiena</strong></td>\r\n<td>Před i po použit&iacute; důkladně omyjte teplou vodou a jemn&yacute;m m&yacute;dlem.</td>\r\n</tr>\r\n<tr>\r\n<td><strong>Bezpečnost</strong></td>\r\n<td>Nepouž&iacute;vejte při po&scaron;kozen&iacute; povrchu v&yacute;robku.</td>\r\n</tr>\r\n<tr>\r\n<td><strong>Reklamace</strong></td>\r\n<td>Z hygienick&yacute;ch důvodů nelze uplatnit reklamaci ani vr&aacute;cen&iacute; zbož&iacute;.</td>\r\n</tr>\r\n<tr>\r\n<td><strong>Uchov&aacute;v&aacute;n&iacute;</strong></td>\r\n<td>Skladujte na such&eacute;m a čist&eacute;m m&iacute;stě, mimo dosah dět&iacute;.</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n</div>', '', 599.00, 'Bílá', NULL, 0, NULL, '{\"nozicky\":[],\"vrsek\":[]}', 'uploads/products/6963d4b2b4ef8_obrázek_2026-01-11_174841290.png', '2026-01-11 17:49:00', 'in_stock', 0, '2026-01-11 16:59:12', '{\"Bílá\":0}', '[]', NULL, 0, NULL, NULL, NULL, 1, '', 0, NULL),
(110, 'Lampa- STRATA', '', 'Skladem', NULL, '<p class=\"p1\"><strong>STRATA</strong> je designov&aacute; lampička založen&aacute; na kontrastu jemn&eacute;ho světla a v&yacute;razn&eacute; struktury. Vrstven&yacute; tvar vytv&aacute;ř&iacute; klidnou atmosf&eacute;ru a nech&aacute;v&aacute; světlo přirozeně rozpt&yacute;lit do prostoru.</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p class=\"p1\">Lampa je navržena jako nadčasov&yacute; interi&eacute;rov&yacute; objekt, kter&yacute; funguje jak zapnut&yacute;, tak i vypnut&yacute;. D&iacute;ky modul&aacute;rn&iacute; konstrukci je možn&eacute; lampu snadno sestavit a jednotliv&eacute; č&aacute;sti do sebe přirozeně zapadaj&iacute;.</p>\r\n<p class=\"p2\">&nbsp;</p>\r\n<ul>\r\n<li><strong>Vrstven&yacute; design</strong> &ndash; charakteristick&aacute; struktura, kter&aacute; pracuje se světlem i st&iacute;nem</li>\r\n<li><strong>Jemn&eacute;, rozpt&yacute;len&eacute; světlo</strong> &ndash; ide&aacute;ln&iacute; pro večern&iacute; atmosf&eacute;ru</li>\r\n<li><strong>Modul&aacute;rn&iacute; konstrukce</strong> &ndash; jednoduch&eacute; sestaven&iacute;</li>\r\n<li><strong>3D tisk</strong> &ndash; každ&yacute; kus je vyroben v mal&yacute;ch s&eacute;ri&iacute;ch</li>\r\n<li><strong>Navrženo a vyrobeno v Česku</strong></li>\r\n</ul>\r\n<p class=\"p2\">&nbsp;</p>\r\n<p class=\"p1\">STRATA je vhodn&aacute; jako lampička na nočn&iacute; stolek, komodu nebo polici. Je určen&aacute; pro ty, kteř&iacute; hledaj&iacute; klidn&eacute; světlo a čist&yacute; design bez zbytečn&yacute;ch detailů.</p>', '<div class=\"product-details-container\">\r\n<h2 class=\"h4 mb-3\">&nbsp;</h2>\r\n<table class=\"table table-bordered table-striped table-sm mb-3\">\r\n<tbody>\r\n<tr>\r\n<th style=\"width: 260px;\">Model</th>\r\n<td>STRATA</td>\r\n</tr>\r\n<tr>\r\n<th>Napět&iacute;</th>\r\n<td>230 V ~ 50 Hz</td>\r\n</tr>\r\n<tr>\r\n<th>Patice ž&aacute;rovky</th>\r\n<td>E14</td>\r\n</tr>\r\n<tr>\r\n<th>Povolen&yacute; světeln&yacute; zdroj</th>\r\n<td>V&Yacute;HRADNĚ LED ž&aacute;rovka</td>\r\n</tr>\r\n<tr>\r\n<th>Maxim&aacute;ln&iacute; př&iacute;kon zdroje</th>\r\n<td>5 W (LED)</td>\r\n</tr>\r\n<tr>\r\n<th>Tř&iacute;da ochrany</th>\r\n<td>II (dvojit&aacute; izolace)</td>\r\n</tr>\r\n<tr>\r\n<th>Nap&aacute;jec&iacute; kabel</th>\r\n<td>Pevně připojen&yacute;, d&eacute;lka 3 m</td>\r\n</tr>\r\n<tr>\r\n<th>Prostřed&iacute;</th>\r\n<td>Pouze pro vnitřn&iacute;, such&eacute; použit&iacute; (IP20)</td>\r\n</tr>\r\n<tr>\r\n<th>Certifikace a shoda</th>\r\n<td>CE, splňuje př&iacute;slu&scaron;n&eacute; normy</td>\r\n</tr>\r\n</tbody>\r\n</table>\r\n<div class=\"alert alert-warning mb-4\" role=\"alert\"><strong>Důležit&eacute; bezpečnostn&iacute; upozorněn&iacute;:</strong> Pro bezpečn&yacute; a dlouholet&yacute; provoz lampy použ&iacute;vejte <strong>V&Yacute;HRADNĚ LED ž&aacute;rovku</strong> s patic&iacute; E14 a maxim&aacute;ln&iacute;m př&iacute;konem <strong>5 W</strong>. Použit&iacute; klasick&eacute; nebo halogenov&eacute; ž&aacute;rovky může způsobit nevratn&eacute; po&scaron;kozen&iacute; lampy a představuje riziko pož&aacute;ru.</div>\r\n<!-- Panel s QR kódem a informacemi -->\r\n<section class=\"info-panel\" aria-labelledby=\"info-panel-title\">\r\n<h3 id=\"info-panel-title\" class=\"info-title\">V&scaron;e důležit&eacute; na jednom m&iacute;stě</h3>\r\n<p class=\"mb-2\">Společně s lampou obdrž&iacute;te unik&aacute;tn&iacute; 3D ti&scaron;těn&yacute; QR k&oacute;d. Po jeho naskenov&aacute;n&iacute; z&iacute;sk&aacute;te okamžit&yacute; př&iacute;stup ke v&scaron;em potřebn&yacute;m informac&iacute;m:</p>\r\n<ul class=\"info-list\">\r\n<li>Kompletn&iacute; n&aacute;vod k použit&iacute; a bezpečnostn&iacute; pokyny</li>\r\n<li>ES Prohl&aacute;&scaron;en&iacute; o shodě (certifikace CE)</li>\r\n<li>Informace o va&scaron;&iacute; objedn&aacute;vce</li>\r\n</ul>\r\n<p class=\"info-note\">Před prvn&iacute;m použit&iacute;m lampy si pros&iacute;m tyto dokumenty pečlivě pročtěte.</p>\r\n</section>\r\n</div>', 649.00, NULL, NULL, 0, '[{\"name\":\"Stínidlo\",\"colors\":[\"Červená\",\"Modrá\",\"Žlutá\",\"Bílá\",\"Transparentní bílá\",\"Transparentní červená\"],\"required\":true},{\"name\":\"Podstavec\",\"colors\":[\"Červená\",\"Modrá\",\"Žlutá\",\"Bílá\",\"Černá\",\"Béžová\",\"Zelená\"],\"required\":true}]', '{\"nozicky\":[],\"vrsek\":[]}', 'https://lh3.googleusercontent.com/d/12nyEqGhNIA7pgXqDMWsbaTbS9YDciXf3,https://lh3.googleusercontent.com/d/1lgm2vpXuxIdDwSFBEoD9TbbN2Fwpmt88,https://lh3.googleusercontent.com/d/1PGbO1k31_Pgb_hbvjpcI7g6yrQHfQfio,https://lh3.googleusercontent.com/d/1nHGvPRB1_sWfHUV4yH1KzayZiMK0yN1P,https://lh3.googleusercontent.com/d/1GHkRtZbTar2QOh5A3sE2OV7NrIvQKC0m,https://lh3.googleusercontent.com/d/1F8qzjoTEZcbMj3AHumnyf2HdEqwF1YfV', '2026-01-14 10:38:00', 'in_stock', 0, '2026-01-14 09:58:53', '{\"Bílá\":0}', '[]', NULL, 0, NULL, NULL, NULL, 0, '', 1, NULL);

-- --------------------------------------------------------

--
-- Struktura tabulky `product_collections_main`
--

CREATE TABLE `product_collections_main` (
  `id` int NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text,
  `image_url` varchar(255) DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `product_collections_main`
--

INSERT INTO `product_collections_main` (`id`, `name`, `slug`, `description`, `image_url`, `icon_url`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Jarní kolekce 2025', 'jarn-kolekce-2025', '', 'https://lh3.googleusercontent.com/d/1VHZYpG3tvtVBr3tHvDzlaEu_fx6kgoqx', 'kolekcefotkyikonky/jarni.webp', 1, '2025-03-20 23:23:11', '2025-12-03 08:07:36'),
(2, 'Monstera kolekce', 'monstera-kolekce', '', 'https://lh3.googleusercontent.com/d/1XGHMwUhNheeTFx7PPDrah6wBasdrM6Bx', 'kolekcefotkyikonky/monstera.webp', 1, '2025-03-23 14:08:32', '2025-12-03 08:07:23'),
(3, 'Lampy', 'lampy', '', 'https://lh3.googleusercontent.com/d/1TnqQFHvmOPLFMqbFsoWYfLlXZr6MeySQ', 'kolekcefotkyikonky/lampy.webp', 1, '2025-03-28 13:34:06', '2025-12-03 08:07:10'),
(4, 'Vázy', 'v-zy', '', 'https://lh3.googleusercontent.com/d/1vEIJktDqn19IhsFqP9xwl1AHYaUQD7IN', 'kolekcefotkyikonky/vazy.webp', 1, '2025-05-14 20:55:06', '2025-12-03 08:06:58'),
(5, 'Květináče', 'kv-tin-e', '', 'https://lh3.googleusercontent.com/d/12J07UhWeJlfDLM8NySTRpcvahye2QQfi', 'kolekcefotkyikonky/kvetinace.webp', 1, '2025-08-20 21:18:11', '2025-12-03 08:06:34'),
(6, 'Ostatní ', 'ostatn', '', 'https://lh3.googleusercontent.com/d/11rkGpqhr60pBfCu8vCBvP2uTOccJhkQN', NULL, 1, '2025-11-18 20:05:41', '2025-12-03 08:06:20'),
(7, 'Vánoce', 'v-noce', '', 'https://lh3.googleusercontent.com/d/18HOWA08HZZjSIBYbwTGDxVLftBUtpErG', NULL, 1, '2025-11-20 14:29:57', '2025-12-03 08:04:14');

-- --------------------------------------------------------

--
-- Struktura tabulky `product_collection_items`
--

CREATE TABLE `product_collection_items` (
  `product_id` int NOT NULL,
  `collection_id` int NOT NULL,
  `position` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `product_collection_items`
--

INSERT INTO `product_collection_items` (`product_id`, `collection_id`, `position`, `created_at`) VALUES
(1, 5, 6, '2025-08-20 21:18:42'),
(2, 5, 5, '2025-08-20 21:18:42'),
(3, 6, 4, '2025-11-18 20:07:45'),
(6, 3, 5, '2025-11-15 16:21:07'),
(7, 3, 6, '2025-11-15 16:21:07'),
(22, 3, 8, '2025-11-15 16:21:07'),
(24, 3, 7, '2025-11-15 16:21:07'),
(25, 3, 4, '2025-11-15 16:21:07'),
(28, 1, 6, '2025-03-20 23:29:21'),
(28, 5, 8, '2025-08-20 21:18:42'),
(29, 1, 3, '2025-03-20 23:29:21'),
(29, 5, 4, '2025-08-20 21:18:42'),
(30, 1, 2, '2025-03-20 23:29:21'),
(30, 5, 2, '2025-08-20 21:18:42'),
(31, 1, 1, '2025-03-20 23:29:21'),
(31, 5, 1, '2025-08-20 21:18:42'),
(32, 1, 4, '2025-03-20 23:29:21'),
(32, 5, 7, '2025-08-20 21:18:42'),
(33, 1, 0, '2025-03-20 23:45:35'),
(33, 5, 3, '2025-08-20 21:18:42'),
(86, 2, 4, '2025-03-28 12:09:26'),
(87, 2, 3, '2025-03-28 12:09:26'),
(88, 2, 2, '2025-03-28 12:09:26'),
(89, 2, 1, '2025-03-28 12:09:26'),
(90, 6, 1, '2025-11-18 20:07:45'),
(91, 6, 2, '2025-11-18 20:07:45'),
(92, 6, 5, '2025-11-18 20:07:45'),
(94, 4, 4, '2025-05-14 21:49:28'),
(95, 4, 1, '2025-05-14 21:49:28'),
(96, 4, 2, '2025-05-14 21:49:28'),
(97, 4, 3, '2025-05-14 21:49:28'),
(98, 3, 2, '2025-11-15 16:21:07'),
(100, 3, 3, '2025-11-15 16:21:07'),
(101, 3, 9, '2025-11-15 16:21:07'),
(102, 3, 1, '2025-11-15 16:21:07'),
(103, 6, 3, '2025-11-18 20:07:45'),
(105, 7, 1, '2025-11-20 14:30:12');

-- --------------------------------------------------------

--
-- Struktura tabulky `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `product_id` int NOT NULL,
  `qr_code` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `order_id`, `product_id`, `qr_code`, `token`, `created_at`, `used_at`) VALUES
(17, '2025082041059', 100, 'QR_2025082041059_100_1756980318', '2d00ed1dd355c17dd46767c1940f42f2', '2025-09-04 10:05:18', NULL),
(18, '2025082041059', 100, 'QR_2025082041059_100_1756980425', '2d00ed1dd355c17dd46767c1940f42f2', '2025-09-04 10:07:05', NULL),
(19, '2025090304574', 97, 'QR_2025090304574_97_1756981248', '642b3cc6bd6e601945a13aaef4b3767a', '2025-09-04 10:20:48', NULL),
(20, '2025090304574', 100, 'QR_2025090304574_100_1756981248', 'd26b89e433a5ef85b8e17b695cb4e63c', '2025-09-04 10:20:48', NULL),
(21, 'KJD-2025-9037', 28, 'QR_KJD-2025-9037_28_1760423996', '9804d9482037c6f9a6a66c042392af8c', '2025-10-14 06:39:56', NULL);

-- --------------------------------------------------------

--
-- Struktura tabulky `recenze`
--

CREATE TABLE `recenze` (
  `id` int NOT NULL,
  `objednavka_cislo` varchar(255) NOT NULL,
  `jmeno` varchar(255) NOT NULL,
  `prijmeni` varchar(255) NOT NULL,
  `text_recenze` text NOT NULL,
  `hodnoceni` int NOT NULL,
  `obrazek` varchar(255) DEFAULT NULL,
  `datum` int DEFAULT NULL
) ;

--
-- Vypisuji data pro tabulku `recenze`
--

INSERT INTO `recenze` (`id`, `objednavka_cislo`, `jmeno`, `prijmeni`, `text_recenze`, `hodnoceni`, `obrazek`, `datum`) VALUES
(8, 'KJD2025716', 'Jan', 'Kubín', 'Miluji jejich lampy. Určitě mohu doporučit', 5, NULL, 1737577455),
(9, '3112025', 'Jiří ', 'Nosek', 'Desingnova lampa neskutečně obohatila náš domov. V prostoru doslova tančí. ', 5, NULL, 1738433315),
(12, 'KJD2025604', 'Dominik ', 'Veselý ', 'Zakoupení lampičky Vám nejen zkrášlí domov, ale i přijde do domu krásná energie. Večerní svícení Vám dodá zajistí energii. Z objednávky lampičky budete mít radost, ale i Vám přinese individuální příběh. ', 5, 'recenze/IMG_3814.jpeg', 1738675844),
(17, '2025052386993', 'Filip', 'Fára', 'DOKONALOST! V realitě to vypadá ještě lépe než na fotkách. DOPORUČUJI.', 5, 'recenze/684b3385b567e_IMG_6218.jpeg', 1749758853),
(18, '2025081553702', 'David', 'K.', 'Velmi kvalitne zpracovany 3D tisk za super cenu. \r\nPri vyberu spravne teploty svetla muze slouzit i jako pouhe ambientni svitidlo do obyvaku, ktere pri vecerni pohode nerusi klidnou atmosferu obyvaku. Skvela vychytavka jsou dve urovne intenzity sviceni. ', 5, 'recenze/68a21344d136f_image.jpg', 1755452228),
(19, 'KJD-2025-0298', 'Zdena', 'J.', 'Lampu Shroom jsem si koupila jen tak ze zvědavosti, protože se mi líbila na fotce, a teda, musím říct, že naživo je ještě hezčí! Vypadá jak malá houbička, taková milá, a když svítí, dělá moc hezký teplý světlo. Večer si ji zapínám místo velké lampy a je to takové útulné, člověk hned má lepší náladu.\r\nTaky mám dvě vázy a ty jsou teda paráda. Každá je trošku jiná, ale obě mají takový pěkný, čistý tvar. Dala jsem do nich sušené květiny a v obýváku to teď vypadá jak z časopisu. Materiál je pevný, ale lehký, a jde vidět, že to dělal někdo, koho to fakt baví.\r\nBalíček přišel v pořádku, hezky zabalený, nic rozbitý. Určitě si ještě něco objednám, ty věci mají prostě kouzlo.', 5, NULL, 1759913780);

-- --------------------------------------------------------

--
-- Struktura tabulky `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `free_shipping_limit` decimal(10,2) DEFAULT '0.00',
  `maintenance_mode` tinyint(1) DEFAULT '0',
  `newsletter_enabled` tinyint(1) DEFAULT '0',
  `newsletter_popup_delay` int DEFAULT '5',
  `newsletter_popup_frequency` int DEFAULT '7',
  `newsletter_always_show` tinyint(1) DEFAULT '0',
  `banner_active` tinyint(1) DEFAULT '0',
  `banner_text` text,
  `banner_bg_color` varchar(10) DEFAULT '#8A6240',
  `banner_text_color` varchar(10) DEFAULT '#ffffff',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `settings`
--

INSERT INTO `settings` (`id`, `contact_email`, `free_shipping_limit`, `maintenance_mode`, `newsletter_enabled`, `newsletter_popup_delay`, `newsletter_popup_frequency`, `newsletter_always_show`, `banner_active`, `banner_text`, `banner_bg_color`, `banner_text_color`, `updated_at`) VALUES
(1, 'info@kubajadesigns.eu', 1500.00, 0, 1, 5, 7, 0, 0, 'Vítejte na našem webu!', '#8A6240', '#ffffff', '2026-01-04 22:24:53');

-- --------------------------------------------------------

--
-- Struktura tabulky `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'Česká republika',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `voucher_balance` decimal(10,2) DEFAULT '0.00',
  `zasilkovna_name` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `isAdmin` varchar(5) NOT NULL DEFAULT 'false'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `first_name`, `last_name`, `phone`, `address`, `city`, `postal_code`, `country`, `created_at`, `last_login`, `is_active`, `voucher_balance`, `zasilkovna_name`, `reset_token`, `isAdmin`) VALUES
(1, 'mickeyjarolim3@gmail.com', '$2y$10$AgTDevtbEGPKIhmunqqIO.qV5/Qo2X3N4aW5e2g6Pd9UKWZCTDGdu', 'Jakub', 'Jarolim', '722341256', 'Mezilesí 2078', 'Praha 9', '19300', 'Česká republika', '2025-03-16 23:30:40', '2025-11-19 08:09:47', 1, 11.00, 'Printea', NULL, 'true'),
(4, 'webepa7007@m3player.com', '$2y$10$86oJNmdcP87445eZRR5Q/OH8/..W/LZ2rFKLVoA3syyvWEZQ8lCOu', 'negrbagr', 'cigos', '222 580 697', 'praga', 'praga', '47874', 'Česká republika', '2025-12-28 15:50:19', '2025-12-28 15:50:29', 1, 0.00, NULL, NULL, 'false');

-- --------------------------------------------------------

--
-- Struktura tabulky `user_favorites`
--

CREATE TABLE `user_favorites` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `user_favorites`
--

INSERT INTO `user_favorites` (`id`, `user_id`, `product_id`, `created_at`) VALUES
(3, 1, 100, '2025-10-09 09:01:36'),
(4, 1, 98, '2025-10-09 09:01:40'),
(5, 1, 97, '2025-10-09 09:01:45');

-- --------------------------------------------------------

--
-- Struktura tabulky `user_wallet`
--

CREATE TABLE `user_wallet` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `balance` decimal(10,2) DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `user_wallet`
--

INSERT INTO `user_wallet` (`id`, `user_id`, `balance`, `created_at`, `updated_at`) VALUES
(1, 1, 50.00, '2025-10-09 11:55:36', '2025-10-09 11:55:36');

-- --------------------------------------------------------

--
-- Struktura tabulky `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int NOT NULL,
  `code` varchar(20) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `note` text,
  `created_by` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `activated_at` timestamp NULL DEFAULT NULL,
  `activated_by` int DEFAULT NULL,
  `status` enum('active','used','expired') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `amount`, `recipient_email`, `order_id`, `note`, `created_by`, `created_at`, `activated_at`, `activated_by`, `status`) VALUES
(1, 'KJD-D96EAABF', 50.00, 'mickeyjarolim9@gmail.com', 'KJD-2025-9037', '', 'Admin', '2025-10-09 11:54:54', '2025-10-09 11:55:36', 1, 'used');

-- --------------------------------------------------------

--
-- Struktura tabulky `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `voucher_id` int DEFAULT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text NOT NULL,
  `order_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

--
-- Vypisuji data pro tabulku `wallet_transactions`
--

INSERT INTO `wallet_transactions` (`id`, `user_id`, `voucher_id`, `type`, `amount`, `description`, `order_id`, `created_at`) VALUES
(1, 1, 1, 'credit', 50.00, 'Aktivace voucheru KJD-D96EAABF', NULL, '2025-10-09 11:55:36');

--
-- Indexy pro exportované tabulky
--

--
-- Indexy pro tabulku `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexy pro tabulku `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_name` (`setting_name`);

--
-- Indexy pro tabulku `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inquiry_id` (`inquiry_id`),
  ADD KEY `sender_type` (`sender_type`),
  ADD KEY `is_read` (`is_read`);

--
-- Indexy pro tabulku `color_notifications`
--
ALTER TABLE `color_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_request` (`product_id`,`product_type`,`color`,`email`);

--
-- Indexy pro tabulku `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `email` (`email`),
  ADD KEY `status` (`status`);

--
-- Indexy pro tabulku `customer_inquiries`
--
ALTER TABLE `customer_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `status` (`status`),
  ADD KEY `chat_token` (`chat_token`);

--
-- Indexy pro tabulku `custom_lightbox_orders`
--
ALTER TABLE `custom_lightbox_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`customer_email`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexy pro tabulku `custom_requests`
--
ALTER TABLE `custom_requests`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `discount_codes`
--
ALTER TABLE `discount_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexy pro tabulku `discount_code_products`
--
ALTER TABLE `discount_code_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `discount_code_id` (`discount_code_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexy pro tabulku `email_templates`
--
ALTER TABLE `email_templates`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `filaments`
--
ALTER TABLE `filaments`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `gopay_payments`
--
ALTER TABLE `gopay_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_order` (`order_id`),
  ADD UNIQUE KEY `unique_gopay` (`gopay_id`),
  ADD KEY `idx_state` (`state`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexy pro tabulku `homepage_content`
--
ALTER TABLE `homepage_content`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexy pro tabulku `invoice_counters`
--
ALTER TABLE `invoice_counters`
  ADD PRIMARY KEY (`period`);

--
-- Indexy pro tabulku `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_id` (`invoice_id`);

--
-- Indexy pro tabulku `invoice_settings`
--
ALTER TABLE `invoice_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `lamps`
--
ALTER TABLE `lamps`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `lamp_ce_files`
--
ALTER TABLE `lamp_ce_files`
  ADD PRIMARY KEY (`lamp_id`,`ce_file_path`);

--
-- Indexy pro tabulku `lamp_components`
--
ALTER TABLE `lamp_components`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexy pro tabulku `newsletter_history`
--
ALTER TABLE `newsletter_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexy pro tabulku `novinky_aplikace`
--
ALTER TABLE `novinky_aplikace`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_id`),
  ADD UNIQUE KEY `tracking_code` (`tracking_code`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_preorder` (`is_preorder`),
  ADD KEY `idx_release_date` (`release_date`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_packeta_packet_id` (`packeta_packet_id`),
  ADD KEY `idx_packeta_barcode` (`packeta_barcode`);

--
-- Indexy pro tabulku `order_cancellations`
--
ALTER TABLE `order_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_number` (`order_number`),
  ADD KEY `email` (`email`),
  ADD KEY `status` (`status`);

--
-- Indexy pro tabulku `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`);

--
-- Indexy pro tabulku `print_calculations`
--
ALTER TABLE `print_calculations`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_is_preorder` (`is_preorder`),
  ADD KEY `idx_stock_status` (`stock_status`),
  ADD KEY `idx_available_from` (`available_from`),
  ADD KEY `idx_release_date` (`release_date`),
  ADD KEY `idx_is_hidden` (`is_hidden`);

--
-- Indexy pro tabulku `product_collections_main`
--
ALTER TABLE `product_collections_main`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexy pro tabulku `product_collection_items`
--
ALTER TABLE `product_collection_items`
  ADD PRIMARY KEY (`product_id`,`collection_id`),
  ADD KEY `collection_id` (`collection_id`);

--
-- Indexy pro tabulku `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_code` (`qr_code`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_qr_code` (`qr_code`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexy pro tabulku `recenze`
--
ALTER TABLE `recenze`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexy pro tabulku `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexy pro tabulku `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_favorite` (`user_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexy pro tabulku `user_wallet`
--
ALTER TABLE `user_wallet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexy pro tabulku `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexy pro tabulku `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `voucher_id` (`voucher_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- AUTO_INCREMENT pro tabulky
--

--
-- AUTO_INCREMENT pro tabulku `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pro tabulku `app_settings`
--
ALTER TABLE `app_settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pro tabulku `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `color_notifications`
--
ALTER TABLE `color_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pro tabulku `complaints`
--
ALTER TABLE `complaints`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `customer_inquiries`
--
ALTER TABLE `customer_inquiries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `custom_lightbox_orders`
--
ALTER TABLE `custom_lightbox_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pro tabulku `custom_requests`
--
ALTER TABLE `custom_requests`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pro tabulku `discount_codes`
--
ALTER TABLE `discount_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT pro tabulku `discount_code_products`
--
ALTER TABLE `discount_code_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pro tabulku `email_templates`
--
ALTER TABLE `email_templates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pro tabulku `filaments`
--
ALTER TABLE `filaments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pro tabulku `gopay_payments`
--
ALTER TABLE `gopay_payments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT pro tabulku `homepage_content`
--
ALTER TABLE `homepage_content`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pro tabulku `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT pro tabulku `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT pro tabulku `lamps`
--
ALTER TABLE `lamps`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pro tabulku `lamp_components`
--
ALTER TABLE `lamp_components`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT pro tabulku `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT pro tabulku `newsletter_history`
--
ALTER TABLE `newsletter_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pro tabulku `newsletter_subscribers`
--
ALTER TABLE `newsletter_subscribers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pro tabulku `novinky_aplikace`
--
ALTER TABLE `novinky_aplikace`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pro tabulku `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT pro tabulku `order_cancellations`
--
ALTER TABLE `order_cancellations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pro tabulku `order_status_history`
--
ALTER TABLE `order_status_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pro tabulku `print_calculations`
--
ALTER TABLE `print_calculations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pro tabulku `product`
--
ALTER TABLE `product`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT pro tabulku `product_collections_main`
--
ALTER TABLE `product_collections_main`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pro tabulku `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT pro tabulku `recenze`
--
ALTER TABLE `recenze`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pro tabulku `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pro tabulku `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pro tabulku `user_favorites`
--
ALTER TABLE `user_favorites`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pro tabulku `user_wallet`
--
ALTER TABLE `user_wallet`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pro tabulku `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pro tabulku `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Omezení pro exportované tabulky
--

--
-- Omezení pro tabulku `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `customer_inquiries` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `discount_code_products`
--
ALTER TABLE `discount_code_products`
  ADD CONSTRAINT `discount_code_products_ibfk_1` FOREIGN KEY (`discount_code_id`) REFERENCES `discount_codes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discount_code_products_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Omezení pro tabulku `product_collection_items`
--
ALTER TABLE `product_collection_items`
  ADD CONSTRAINT `product_collection_items_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_collection_items_ibfk_2` FOREIGN KEY (`collection_id`) REFERENCES `product_collections_main` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD CONSTRAINT `qr_codes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Omezení pro tabulku `user_favorites`
--
ALTER TABLE `user_favorites`
  ADD CONSTRAINT `user_favorites_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_favorites_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`);

--
-- Omezení pro tabulku `user_wallet`
--
ALTER TABLE `user_wallet`
  ADD CONSTRAINT `user_wallet_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Omezení pro tabulku `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `wallet_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wallet_transactions_ibfk_2` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
