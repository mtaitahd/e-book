-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 30, 2026 at 09:08 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.35

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ebs`
--

-- --------------------------------------------------------

--
-- Table structure for table `authors`
--

CREATE TABLE `authors` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `bio` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `authors`
--

INSERT INTO `authors` (`id`, `name`, `slug`, `status`, `bio`, `created_at`, `updated_at`) VALUES
(1, 'Grace Nakato', 'grace-nakato', 'active', 'Writer and lecturer focused on African technology adoption.', '2026-09-23 06:06:26', '2026-09-23 06:06:26'),
(2, 'Brian Otieno', 'brian-otieno', 'active', 'Software engineer and author of practical programming guides.', '2026-09-23 06:06:27', '2026-09-23 06:06:27'),
(3, 'Amara Wanjiru', 'amara-wanjiru', 'inactive', 'Business strategist writing on entrepreneurship and growth.', '2026-09-23 06:06:27', '2026-09-25 18:05:22'),
(4, 'Smoke Test Author', 'smoke-test-author', 'inactive', 'Created during the Stage 5 live smoke test.', '2026-09-23 07:06:54', '2026-09-23 07:09:30'),
(5, 'Smoke Test Author', 'smoke-test-author-2', 'inactive', 'Created during the Stage 5 live smoke test.', '2026-09-23 07:07:38', '2026-09-23 07:10:46'),
(6, 'Smoke Test Author 1790158165', 'smoke-test-author-1790158165', 'inactive', 'Created during the Stage 5 live smoke test.', '2026-09-23 07:09:25', '2026-09-23 07:09:26');

-- --------------------------------------------------------

--
-- Table structure for table `author_book`
--

CREATE TABLE `author_book` (
  `author_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `author_book`
--

INSERT INTO `author_book` (`author_id`, `book_id`) VALUES
(1, 3),
(1, 4),
(2, 1),
(2, 3),
(2, 13),
(3, 2);

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pricing_type` varchar(255) NOT NULL DEFAULT 'paid',
  `cover_image` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_type` varchar(255) DEFAULT NULL,
  `book_format` varchar(20) NOT NULL DEFAULT 'pdf',
  `publisher` varchar(255) DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `slug`, `description`, `price`, `pricing_type`, `cover_image`, `file_path`, `file_type`, `book_format`, `publisher`, `published_at`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Modern Web Development', 'modern-web-development', 'A practical guide to building modern web applications with PHP and Laravel.', 0.00, 'free', 'covers/SDOjulxAvmzcbpvpgDaplVVKS9oNc17CG5eFzlAJ.jpg', 'ebooks/Jzc4NHmbuvM09yJIuGDhsEx8RDKY5JdQdj39VVVR.pdf', 'pdf', 'pdf', 'TechServe Publishing', '2026-09-30 07:00:00', 'published', '2026-09-23 06:06:27', '2026-09-30 18:42:56'),
(2, 'Grow Your Startup', 'grow-your-startup', 'Lessons on building and scaling a small business in an emerging market.', 1200.00, 'paid', NULL, NULL, 'epub', 'pdf', 'Amara Press', NULL, 'published', '2026-09-23 06:06:28', '2026-09-23 06:06:28'),
(3, 'Data Skills for Africa', 'data-skills-for-africa', 'An introduction to data literacy with local case studies.', 2000.00, 'paid', NULL, NULL, 'pdf', 'pdf', 'TechServe Publishing', NULL, 'published', '2026-09-23 06:06:28', '2026-09-23 06:06:28'),
(4, 'Draft Technical Manual', 'draft-technical-manual', 'An unpublished sample title that must not appear on the public storefront.', 0.00, 'free', NULL, NULL, 'pdf', 'pdf', NULL, NULL, 'draft', '2026-09-23 06:06:29', '2026-09-23 06:06:29'),
(10, 'distinctio autem esse neque', 'distinctio-autem-esse-neque', 'Similique exercitationem voluptatem molestiae suscipit rerum. Dolorem illo quod distinctio nisi sed molestiae. Rerum quasi vitae id doloremque. Similique laudantium voluptatum inventore nobis. Et nam recusandae qui vero non voluptas maxime necessitatibus.', 5523.55, 'paid', NULL, NULL, 'pdf', 'pdf', 'Goyette, Schmidt and Glover', '2026-04-08 17:23:51', 'draft', '2026-09-25 17:23:51', '2026-09-25 17:23:51'),
(11, 'possimus vitae iure et', 'possimus-vitae-iure-et', 'Excepturi ut quaerat molestiae repellat. Dignissimos et debitis illo. Voluptatem hic sed maiores provident voluptatem. Dolorem laudantium et est dolor ut atque incidunt.', 8085.20, 'paid', NULL, NULL, 'pdf', 'pdf', 'Nitzsche, Ortiz and Beer', '2026-09-10 17:24:00', 'draft', '2026-09-25 17:24:00', '2026-09-25 17:24:00'),
(12, 'et mollitia magnam impedit', 'et-mollitia-magnam-impedit', 'Rerum odit non sequi omnis rerum rerum placeat. Et veniam atque voluptatem sed quis aut. Sit dignissimos perspiciatis et.', 3941.84, 'paid', NULL, NULL, 'pdf', 'pdf', 'Effertz, Hagenes and Willms', '2026-05-21 17:24:06', 'draft', '2026-09-25 17:24:06', '2026-09-25 17:24:06'),
(13, 'The Worker', 'the-worker', 'The man in work', 0.00, 'free', 'covers/2WENssIcdKKwcSl1KmQ2KI0FaUHQ9TgffmTMaMH0.jpg', 'ebooks/7wk4olY8IBgESpM8nKSJYfeTzYuF4WewU96UghKI.pdf', 'pdf', 'pdf', 'Mtaita Tech', '2026-09-24 07:00:00', 'published', '2026-09-25 17:52:15', '2026-09-30 18:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `book_category`
--

CREATE TABLE `book_category` (
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `book_category`
--

INSERT INTO `book_category` (`book_id`, `category_id`) VALUES
(1, 2),
(2, 2),
(3, 1),
(4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `book_chapters`
--

CREATE TABLE `book_chapters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Programming', 'programming', 'Software development and engineering titles.', 'active', '2026-09-23 06:06:27', '2026-09-23 06:06:27'),
(2, 'Business', 'business', 'Entrepreneurship, finance and management titles.', 'active', '2026-09-23 06:06:27', '2026-09-23 06:06:27'),
(3, 'Fiction', 'fiction', 'Fictional stories and novels.', 'active', '2026-09-23 06:06:27', '2026-09-23 06:06:27');

-- --------------------------------------------------------

--
-- Table structure for table `download_logs`
--

CREATE TABLE `download_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `purchase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `book_id` bigint(20) UNSIGNED DEFAULT NULL,
  `downloaded_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `download_logs`
--

INSERT INTO `download_logs` (`id`, `user_id`, `purchase_id`, `book_id`, `downloaded_at`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(2, NULL, NULL, NULL, '2026-09-24 06:22:11', '127.0.0.1', 'curl/8.21.0', '2026-09-24 06:22:11', '2026-09-24 06:22:11'),
(3, NULL, NULL, NULL, '2026-09-24 06:24:31', '127.0.0.1', 'curl/8.21.0', '2026-09-24 06:24:31', '2026-09-24 06:24:31'),
(4, NULL, NULL, NULL, '2026-09-24 06:25:23', '127.0.0.1', 'curl/8.21.0', '2026-09-24 06:25:23', '2026-09-24 06:25:23'),
(5, NULL, NULL, NULL, '2026-09-24 06:30:23', '127.0.0.1', 'curl/8.21.0', '2026-09-24 06:30:23', '2026-09-24 06:30:23'),
(6, NULL, NULL, NULL, '2026-09-24 06:31:53', '127.0.0.1', 'curl/8.21.0', '2026-09-24 06:31:53', '2026-09-24 06:31:53');

-- --------------------------------------------------------

--
-- Table structure for table `email_notifications`
--

CREATE TABLE `email_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `recipient` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_23_000001_add_role_and_status_to_users_table', 2),
(5, '2026_09_23_000002_create_authors_table', 2),
(6, '2026_09_23_000003_create_categories_table', 2),
(7, '2026_09_23_000004_create_books_table', 2),
(8, '2026_09_23_000005_create_author_book_table', 2),
(9, '2026_09_23_000006_create_book_category_table', 2),
(10, '2026_09_23_000007_add_status_to_authors_table', 3),
(11, '2026_09_23_000008_create_orders_table', 4),
(12, '2026_09_23_000009_create_order_items_table', 4),
(13, '2026_09_23_000010_add_customer_payment_fields_to_users_table', 5),
(14, '2026_09_23_000011_create_payments_table', 5),
(15, '2026_09_23_000012_create_webhook_events_table', 5),
(16, '2026_09_23_000013_add_paid_at_to_orders_table', 6),
(17, '2026_09_23_000014_fix_webhook_events_received_at_auto_update', 7),
(18, '2026_09_23_000015_create_purchases_table', 8),
(19, '2026_09_23_000016_create_download_logs_table', 9),
(20, '2026_09_24_000001_create_reading_progress_table', 10),
(21, '2026_09_25_000001_add_book_format_to_books_table', 11),
(22, '2026_09_25_000002_create_book_chapters_table', 11),
(23, '2026_09_25_000003_create_reading_bookmarks_table', 11),
(24, '2026_09_25_000004_add_chapter_to_reading_progress_table', 11),
(25, '2026_09_29_000001_add_pricing_type_to_books_table', 12),
(26, '2026_09_29_000002_create_email_notifications_table', 12),
(27, '2026_09_29_000003_add_status_paid_at_index_to_orders_table', 12),
(28, '2026_09_29_000004_create_payment_settings_table', 12),
(29, '2026_09_30_000001_add_unique_index_to_users_phone', 13),
(30, '2026_09_30_000002_create_subscribers_table', 14);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(255) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(8) NOT NULL DEFAULT 'KES',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_number`, `subtotal`, `total`, `currency`, `status`, `paid_at`, `created_at`, `updated_at`) VALUES
(1, 2, 'EBS-20260923-7655', 1500.00, 1500.00, 'KES', 'pending', NULL, '2026-09-23 07:41:40', '2026-09-23 07:41:40'),
(2, 2, 'EBS-20260923-9540', 4700.00, 4700.00, 'KES', 'pending', NULL, '2026-09-23 07:42:30', '2026-09-23 07:42:30'),
(3, 2, 'EBS-20260923-4589', 1500.00, 1500.00, 'TZS', 'pending', NULL, '2026-09-23 09:01:48', '2026-09-23 09:01:48'),
(4, 3, 'EBS-20260923-4581', 2000.00, 2000.00, 'TZS', 'pending', NULL, '2026-09-23 09:25:00', '2026-09-23 09:25:00'),
(5, 2, 'EBS-20260923-0377', 1500.00, 1500.00, 'TZS', 'pending', NULL, '2026-09-23 09:45:24', '2026-09-23 09:45:24'),
(6, 3, 'EBS-20260923-2650', 1500.00, 1500.00, 'TZS', 'pending', NULL, '2026-09-23 09:52:59', '2026-09-23 09:52:59'),
(11, 7, 'EBS-20260924-9641', 1500.00, 1500.00, 'TZS', 'pending', NULL, '2026-09-24 07:28:08', '2026-09-24 07:28:08'),
(12, 7, 'EBS-20260924-8980', 2000.00, 2000.00, 'TZS', 'pending', NULL, '2026-09-24 10:47:56', '2026-09-24 10:47:56'),
(13, 7, 'EBS-20260924-8999', 1200.00, 1200.00, 'TZS', 'pending', NULL, '2026-09-24 10:58:02', '2026-09-24 10:58:02'),
(14, 7, 'EBS-20260925-0653', 2000.00, 2000.00, 'TZS', 'pending', NULL, '2026-09-25 15:39:10', '2026-09-25 15:39:10'),
(15, 1, 'EBS-20260925-5648', 2000.00, 2000.00, 'TZS', 'pending', NULL, '2026-09-25 17:46:56', '2026-09-25 17:46:56'),
(16, 1, 'EBS-20260930-7058', 200.00, 200.00, 'TZS', 'pending', NULL, '2026-09-30 16:12:12', '2026-09-30 16:12:12'),
(17, 1, 'EBS-20260930-6120', 1500.00, 1500.00, 'TZS', 'pending', NULL, '2026-09-30 16:12:46', '2026-09-30 16:12:46'),
(18, 13, 'EBS-20260930-6639', 1200.00, 1200.00, 'TZS', 'pending', NULL, '2026-09-30 16:15:37', '2026-09-30 16:15:37'),
(19, 13, 'EBS-20260930-5860', 1500.00, 1500.00, 'TZS', 'pending', NULL, '2026-09-30 16:37:11', '2026-09-30 16:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `book_id`, `quantity`, `unit_price`, `subtotal`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1500.00, 1500.00, '2026-09-23 07:41:40', '2026-09-23 07:41:40'),
(2, 2, 1, 1, 1500.00, 1500.00, '2026-09-23 07:42:30', '2026-09-23 07:42:30'),
(3, 2, 2, 1, 1200.00, 1200.00, '2026-09-23 07:42:30', '2026-09-23 07:42:30'),
(4, 2, 3, 1, 2000.00, 2000.00, '2026-09-23 07:42:30', '2026-09-23 07:42:30'),
(5, 3, 1, 1, 1500.00, 1500.00, '2026-09-23 09:01:48', '2026-09-23 09:01:48'),
(6, 4, 3, 1, 2000.00, 2000.00, '2026-09-23 09:25:00', '2026-09-23 09:25:00'),
(7, 5, 1, 1, 1500.00, 1500.00, '2026-09-23 09:45:24', '2026-09-23 09:45:24'),
(8, 6, 1, 1, 1500.00, 1500.00, '2026-09-23 09:53:00', '2026-09-23 09:53:00'),
(13, 11, 1, 1, 1500.00, 1500.00, '2026-09-24 07:28:09', '2026-09-24 07:28:09'),
(14, 12, 3, 1, 2000.00, 2000.00, '2026-09-24 10:47:56', '2026-09-24 10:47:56'),
(15, 13, 2, 1, 1200.00, 1200.00, '2026-09-24 10:58:02', '2026-09-24 10:58:02'),
(16, 14, 3, 1, 2000.00, 2000.00, '2026-09-25 15:39:10', '2026-09-25 15:39:10'),
(17, 15, 3, 1, 2000.00, 2000.00, '2026-09-25 17:46:56', '2026-09-25 17:46:56'),
(18, 16, 13, 1, 200.00, 200.00, '2026-09-30 16:12:12', '2026-09-30 16:12:12'),
(19, 17, 1, 1, 1500.00, 1500.00, '2026-09-30 16:12:46', '2026-09-30 16:12:46'),
(20, 18, 2, 1, 1200.00, 1200.00, '2026-09-30 16:15:37', '2026-09-30 16:15:37'),
(21, 19, 1, 1, 1500.00, 1500.00, '2026-09-30 16:37:11', '2026-09-30 16:37:11');

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(255) NOT NULL DEFAULT 'snippe',
  `payment_type` varchar(255) NOT NULL DEFAULT 'mobile',
  `provider_reference` varchar(255) DEFAULT NULL,
  `external_reference` varchar(255) DEFAULT NULL,
  `idempotency_key` varchar(255) NOT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'TZS',
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `channel_provider` varchar(255) DEFAULT NULL,
  `failure_reason` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `provider_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`provider_payload`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `order_id`, `provider`, `payment_type`, `provider_reference`, `external_reference`, `idempotency_key`, `amount`, `currency`, `status`, `channel_provider`, `failure_reason`, `expires_at`, `paid_at`, `provider_payload`, `created_at`, `updated_at`) VALUES
(1, 17, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-1', 1500, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again.', NULL, NULL, NULL, '2026-09-30 16:12:55', '2026-09-30 16:12:58'),
(2, 17, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-2', 1500, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again.', NULL, NULL, NULL, '2026-09-30 16:13:11', '2026-09-30 16:13:13'),
(3, 18, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-3', 1200, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again.', NULL, NULL, NULL, '2026-09-30 16:15:45', '2026-09-30 16:15:47'),
(4, 18, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-4', 1200, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again.', NULL, NULL, NULL, '2026-09-30 16:15:58', '2026-09-30 16:16:00'),
(5, 19, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-5', 1500, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again. (VAL_001 webhook_url webhook URL must use HTTPS)', NULL, NULL, NULL, '2026-09-30 16:37:57', '2026-09-30 16:37:59'),
(6, 19, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-6', 1500, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again. (VAL_001 webhook_url webhook URL must use HTTPS)', NULL, NULL, NULL, '2026-09-30 16:37:57', '2026-09-30 16:37:59'),
(7, 19, 'snippe', 'mobile', NULL, NULL, 'ebs-pay-7', 1500, 'TZS', 'pending', NULL, 'request_failed:The payment request could not be processed. Please try again. (VAL_001 webhook_url webhook URL must use HTTPS)', NULL, NULL, NULL, '2026-09-30 16:38:25', '2026-09-30 16:38:27');

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider` varchar(32) NOT NULL,
  `api_key` text DEFAULT NULL,
  `webhook_secret` text DEFAULT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `verify_on_webhook` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_settings`
--

INSERT INTO `payment_settings` (`id`, `provider`, `api_key`, `webhook_secret`, `webhook_url`, `enabled`, `verify_on_webhook`, `updated_by`, `created_at`, `updated_at`) VALUES
(2, 'snippe', 'eyJpdiI6InRuRHlQaFRtY1dKN0ZEWjd3ckRXOEE9PSIsInZhbHVlIjoiWDhhT0Nwa0lvU0RRdGkxeDE2aTFNZnJXMnhEZDdoVkFEbnBhSVY0Y0hpUlpBaXRjQnhoVGNiL2NodmcvTSt5M1hvUFpMdTBYOWlITWxxWm5SYWppMFYySzZaRmpGQXVtZ05IUjFISXVVcWs9IiwibWFjIjoiY2IwN2RjMmI5NmQ1ZTc4ZDAyOWY3MWU4YTYxNDVjNzMzN2NjMGE2NGQxZjhkN2JjZjkxZDAzMmE2ZjRhNmZjMyIsInRhZyI6IiJ9', 'eyJpdiI6InlOakdvWkpqNFVRKysrVXp5YTN6SFE9PSIsInZhbHVlIjoiSW9VWUhlcVVGZVkyOUdYWXNyNDRLLzM0K3BTZXJPYWNhcHFKd3hrMk83RDVuMTcwRnZqUi83NmRMalFrMUR4NEZMNDZXVCtSbmpMN3ZWQjY5QWJMc0ZDUVNSOEhKZld0UE8rTFNxMHdEUzQ9IiwibWFjIjoiZjM0ZmI5ZjdjNGM5NDhkMzNhYzIyNGFlZWY0ZjNkMjk2MmQxOWQ1ZDc4OGVlOTc0OTA0YzBiYzdiZDViMmQyMyIsInRhZyI6IiJ9', 'http://localhost/e-book/public/webhooks/snippe', 1, 1, 1, '2026-09-30 15:57:33', '2026-09-30 15:57:33');

-- --------------------------------------------------------

--
-- Table structure for table `purchases`
--

CREATE TABLE `purchases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(3) NOT NULL DEFAULT 'TZS',
  `purchased_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_bookmarks`
--

CREATE TABLE `reading_bookmarks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `chapter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `page` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `label` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `position_key` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reading_progress`
--

CREATE TABLE `reading_progress` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_id` bigint(20) UNSIGNED NOT NULL,
  `book_id` bigint(20) UNSIGNED NOT NULL,
  `chapter_id` bigint(20) UNSIGNED DEFAULT NULL,
  `current_page` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('Po6Tjg65tWJkzUchPpV95j7fSKKhyVuiptiHFQYE', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36', 'YToyOntzOjY6Il90b2tlbiI7czo0MDoiNDNuS1FjYWV1QnprdjdHNUJNeDh6RnBuTjFqY2VwMFhvQnRadTBHMSI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1790752798);

-- --------------------------------------------------------

--
-- Table structure for table `subscribers`
--

CREATE TABLE `subscribers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `subscribed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(32) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'customer',
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `first_name`, `last_name`, `phone`, `email`, `email_verified_at`, `password`, `role`, `status`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Dev Admin', NULL, NULL, '255616591639', 'admin@ebs.local', '2026-09-23 06:06:23', '$2y$12$q8ZpnSPpb3MgEAv3NnXvqe1spacGrWMPq03uhuqNWEw8Y3dBP5yee', 'admin', 'active', 'wuV2dYsS9NDCkucTqXVPcIGxa864CuBu5YLEL95UiqwKrDqjullcgnjTuTPh', '2026-09-23 06:06:24', '2026-09-30 16:12:55'),
(2, 'Dev Customer', NULL, NULL, NULL, 'customer@ebs.local', '2026-09-23 06:06:24', '$2y$12$1iAwxOlpYdrPYxyOFdR14.BRdP7AIaCjIEs6.SM3OG2cz1n.w/NXq', 'customer', 'active', NULL, '2026-09-23 06:06:26', '2026-09-23 06:06:26'),
(3, 'Johnson Mtaita', NULL, NULL, NULL, 'mtaitahd@gmail.com', NULL, '$2y$12$Ry25YDkP5WilCpLaHZphL.gltwwBldWd2GnKL0SLiNG3cmkuyAdCS', 'customer', 'active', NULL, '2026-09-23 08:51:40', '2026-09-23 08:51:40'),
(7, 'YENU MUSSA KATAMBO', NULL, NULL, NULL, 'aishaamiri440@gmail.com', NULL, '$2y$12$w2aVi1dQu6rAu5Q4iMPJm.6nP9/YWqiCC.ZiJkoua2U5QMYfcDGpG', 'customer', 'active', NULL, '2026-09-24 07:27:27', '2026-09-24 07:27:27'),
(8, 'Mr. Kristopher Morar', NULL, NULL, NULL, 'diamond26@example.org', '2026-09-25 17:23:37', '$2y$12$7MMzZiy73joNph/isOBOPOWyqQyhhxYzYy1OnJw5bm4xz4if4XiLK', 'admin', 'active', 'GZex8Xktxp', '2026-09-25 17:23:37', '2026-09-25 17:23:37'),
(9, 'Alexie O\'Conner', NULL, NULL, NULL, 'izabella68@example.net', '2026-09-25 17:23:51', '$2y$12$/aZMoB4T8DdAvWI6XMtbgufBYIbi.4jC0/0Eik1bRTb.IOtxSnBD.', 'admin', 'active', 'oLH5UthzKS', '2026-09-25 17:23:51', '2026-09-25 17:23:51'),
(10, 'Dr. Kirstin Dicki IV', NULL, NULL, NULL, 'willms.dariana@example.com', '2026-09-25 17:23:59', '$2y$12$2MzJuGh3SqryQj1skmbrfuz1ZA4Td4gq0nRwxo.AHN7sJ/L1OqdiK', 'admin', 'active', 'GgCnmSG8Rx', '2026-09-25 17:24:00', '2026-09-25 17:24:00'),
(11, 'Clara Smith', NULL, NULL, NULL, 'connie.frami@example.com', '2026-09-25 17:24:06', '$2y$12$qXp7DhX0gaaSAmkgzNTqxumzLUqDEtAYdsTP3V9ImGG2dLV1TylVO', 'admin', 'active', 'WYzHQ3lA4l', '2026-09-25 17:24:06', '2026-09-25 17:24:06'),
(12, 'Baraka Baraka', NULL, NULL, NULL, 'barakanikundiwe14@gmail.com', NULL, '$2y$12$UOYHOniJAgIB4oPVgxKBHuf3XSjtjWU2/nZM7iUdVoOZS2oHMCnqy', 'customer', 'active', NULL, '2026-09-30 15:47:37', '2026-09-30 15:47:37'),
(13, 'Baraka Baraka', NULL, NULL, '255743424254', 'barakanikundiwe@gmail.com', NULL, '$2y$12$o8oYqZSCYE/Hb08brsd/suY/d9UZTnhzE89RiPrGOFZ3XiNPi/L7.', 'customer', 'active', NULL, '2026-09-30 16:15:22', '2026-09-30 16:15:22'),
(15, 'Shell Test', NULL, NULL, NULL, 'shelltest@example.com', NULL, '$2y$12$d8f5ykJr/4kR6HCerGT7nuJQsFSdPXdRYpkbFfCht/D7v4387ZpwG', 'admin', 'active', NULL, '2026-09-30 18:41:28', '2026-09-30 18:41:41');

-- --------------------------------------------------------

--
-- Table structure for table `webhook_events`
--

CREATE TABLE `webhook_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `event_id` varchar(255) NOT NULL,
  `event_type` varchar(255) NOT NULL,
  `provider_reference` varchar(255) DEFAULT NULL,
  `payload_hash` varchar(64) NOT NULL,
  `received_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `processed_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'received',
  `failure_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `authors`
--
ALTER TABLE `authors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `authors_slug_unique` (`slug`);

--
-- Indexes for table `author_book`
--
ALTER TABLE `author_book`
  ADD PRIMARY KEY (`author_id`,`book_id`),
  ADD KEY `author_book_book_id_foreign` (`book_id`);

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `books_slug_unique` (`slug`),
  ADD KEY `books_status_index` (`status`),
  ADD KEY `books_pricing_type_index` (`pricing_type`);

--
-- Indexes for table `book_category`
--
ALTER TABLE `book_category`
  ADD PRIMARY KEY (`book_id`,`category_id`),
  ADD KEY `book_category_category_id_foreign` (`category_id`);

--
-- Indexes for table `book_chapters`
--
ALTER TABLE `book_chapters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `book_chapters_book_id_slug_unique` (`book_id`,`slug`),
  ADD KEY `book_chapters_book_id_position_index` (`book_id`,`position`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `categories_slug_unique` (`slug`);

--
-- Indexes for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `download_logs_purchase_id_foreign` (`purchase_id`),
  ADD KEY `download_logs_book_id_foreign` (`book_id`),
  ADD KEY `download_logs_user_id_book_id_index` (`user_id`,`book_id`);

--
-- Indexes for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_notifications_order_id_type_unique` (`order_id`,`type`),
  ADD KEY `email_notifications_payment_id_foreign` (`payment_id`),
  ADD KEY `email_notifications_user_id_index` (`user_id`),
  ADD KEY `email_notifications_status_index` (`status`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `orders_order_number_unique` (`order_number`),
  ADD KEY `orders_user_id_foreign` (`user_id`),
  ADD KEY `orders_status_index` (`status`),
  ADD KEY `orders_status_paid_at_index` (`status`,`paid_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_items_order_id_book_id_unique` (`order_id`,`book_id`),
  ADD KEY `order_items_book_id_foreign` (`book_id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payments_idempotency_key_unique` (`idempotency_key`),
  ADD UNIQUE KEY `payments_provider_reference_unique` (`provider_reference`),
  ADD KEY `payments_order_id_status_index` (`order_id`,`status`),
  ADD KEY `payments_status_index` (`status`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_settings_provider_unique` (`provider`);

--
-- Indexes for table `purchases`
--
ALTER TABLE `purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `purchases_order_item_id_unique` (`order_item_id`),
  ADD UNIQUE KEY `purchases_user_id_order_id_book_id_unique` (`user_id`,`order_id`,`book_id`),
  ADD KEY `purchases_order_id_foreign` (`order_id`),
  ADD KEY `purchases_book_id_foreign` (`book_id`);

--
-- Indexes for table `reading_bookmarks`
--
ALTER TABLE `reading_bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reading_bookmarks_purchase_id_position_key_unique` (`purchase_id`,`position_key`),
  ADD KEY `reading_bookmarks_book_id_foreign` (`book_id`),
  ADD KEY `reading_bookmarks_chapter_id_foreign` (`chapter_id`),
  ADD KEY `reading_bookmarks_user_id_book_id_index` (`user_id`,`book_id`);

--
-- Indexes for table `reading_progress`
--
ALTER TABLE `reading_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reading_progress_purchase_id_unique` (`purchase_id`),
  ADD KEY `reading_progress_book_id_foreign` (`book_id`),
  ADD KEY `reading_progress_user_id_book_id_index` (`user_id`,`book_id`),
  ADD KEY `reading_progress_chapter_id_foreign` (`chapter_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscribers_email_unique` (`email`),
  ADD KEY `subscribers_status_index` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_phone_unique` (`phone`);

--
-- Indexes for table `webhook_events`
--
ALTER TABLE `webhook_events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `webhook_events_event_id_unique` (`event_id`),
  ADD KEY `webhook_events_provider_reference_event_type_index` (`provider_reference`,`event_type`),
  ADD KEY `webhook_events_status_index` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `authors`
--
ALTER TABLE `authors`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `book_chapters`
--
ALTER TABLE `book_chapters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `download_logs`
--
ALTER TABLE `download_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `email_notifications`
--
ALTER TABLE `email_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `purchases`
--
ALTER TABLE `purchases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reading_bookmarks`
--
ALTER TABLE `reading_bookmarks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reading_progress`
--
ALTER TABLE `reading_progress`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `webhook_events`
--
ALTER TABLE `webhook_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `author_book`
--
ALTER TABLE `author_book`
  ADD CONSTRAINT `author_book_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `authors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `author_book_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `book_category`
--
ALTER TABLE `book_category`
  ADD CONSTRAINT `book_category_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_category_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `book_chapters`
--
ALTER TABLE `book_chapters`
  ADD CONSTRAINT `book_chapters_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `download_logs`
--
ALTER TABLE `download_logs`
  ADD CONSTRAINT `download_logs_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `download_logs_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `download_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `email_notifications`
--
ALTER TABLE `email_notifications`
  ADD CONSTRAINT `email_notifications_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_notifications_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `email_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`),
  ADD CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchases`
--
ALTER TABLE `purchases`
  ADD CONSTRAINT `purchases_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`),
  ADD CONSTRAINT `purchases_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchases_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_bookmarks`
--
ALTER TABLE `reading_bookmarks`
  ADD CONSTRAINT `reading_bookmarks_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_bookmarks_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `book_chapters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_bookmarks_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_bookmarks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_progress`
--
ALTER TABLE `reading_progress`
  ADD CONSTRAINT `reading_progress_book_id_foreign` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_progress_chapter_id_foreign` FOREIGN KEY (`chapter_id`) REFERENCES `book_chapters` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reading_progress_purchase_id_foreign` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_progress_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
