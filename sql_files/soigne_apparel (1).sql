-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 26, 2026 at 02:05 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `soigne_apparel`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `all_costs`
-- (See below for the actual view)
--
CREATE TABLE `all_costs` (
`cost_month` varchar(10)
,`category` varchar(11)
,`amount` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `cart_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`cart_id`, `user_id`, `variant_id`, `quantity`) VALUES
(27, 4, 339, 1);

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `name`) VALUES
(8, 'Chinos'),
(4, 'Hoodies'),
(7, 'Jeans'),
(10, 'Outerwear'),
(6, 'Pants'),
(3, 'Polos'),
(2, 'Shirts'),
(9, 'Shorts'),
(5, 'Sweaters'),
(1, 'T-shirts');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `customer_name` varchar(120) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`customer_id`, `user_id`, `customer_name`, `phone`, `address`) VALUES
(1, 2, 'Juan Dela Cruz', '09171234567', '123 Sample St., Quezon City, Metro Manila'),
(3, 4, 'rexter baldo triste', '09493201926', 'sahing st. western bicutan');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `expense_date` date NOT NULL,
  `billing_month` date NOT NULL,
  `category` enum('rent','electricity','water','internet','salaries') NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL
) ;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`expense_id`, `expense_date`, `billing_month`, `category`, `description`, `amount`) VALUES
(1, '2026-09-05', '2026-09-01', 'electricity', 'September electricity', 3200.00);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `fulfillment_status` enum('pending','shipped','delivered','failed_delivery','cancelled') NOT NULL DEFAULT 'pending',
  `payment_status` enum('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  `shipping_address` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`order_id`, `user_id`, `fulfillment_status`, `payment_status`, `shipping_address`, `created_at`) VALUES
(7, 4, 'pending', 'unpaid', 'sahing st. western bicutan', '2026-10-22 11:08:17');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `variant_id`, `quantity`, `price`) VALUES
(8, 7, 342, 2, 499.00);

-- --------------------------------------------------------

--
-- Stand-in structure for view `order_totals`
-- (See below for the actual view)
--
CREATE TABLE `order_totals` (
`order_id` int(11)
,`total` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `premium_payments`
--

CREATE TABLE `premium_payments` (
  `payment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `receipt_filename` varchar(64) NOT NULL,
  `receipt_mime` varchar(30) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `department` enum('men','women','kids') NOT NULL DEFAULT 'men',
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `supplier_id`, `name`, `category_id`, `department`, `price`, `description`, `created_at`) VALUES
(6, 5, 'WINDPROOF AND WATER-REPELLENT JACKET', 10, 'men', 2490.00, NULL, '2026-10-15 10:57:27'),
(7, 5, 'BAGGY DENIM SHORTS', 9, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(8, 5, 'REGULAR-FIT LINEN-BLEND SHORTS', 9, 'men', 1490.00, NULL, '2026-10-15 10:57:27'),
(9, 5, 'RELAXED-FIT DENIM SHORTS', 9, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(10, 5, 'LOOSE-FIT COTTON CARGO SHORTS', 9, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(11, 5, 'REGULAR-FIT SWEATSHORTS', 9, 'men', 699.00, NULL, '2026-10-15 10:57:27'),
(12, 5, 'RELAXED-FIT COTTON CHINOS', 8, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(13, 5, 'RELAXED-FIT COTTON TWILL CHINOS', 8, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(14, 5, 'RELAXED JEANS', 7, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(15, 5, 'ULTRA-BAGGY JEANS', 7, 'men', 2490.00, NULL, '2026-10-15 10:57:27'),
(16, 5, 'ORIGINAL STRAIGHT JEANS', 7, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(17, 5, 'BAGGY JEANS', 7, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(18, 5, 'REGULAR-FIT FINE-KNIT TURTLENECK SWEATER', 5, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(19, 5, 'REGULAR-FIT FINE-KNIT SWEATER', 5, 'men', 1290.00, NULL, '2026-10-15 10:57:27'),
(20, 5, 'REGULAR-FIT CARDIGAN', 5, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(21, 5, 'REGULAR-FIT HALF-ZIP SWEATER', 5, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(22, 5, 'OVERSIZED PRINTED HOODIE', 4, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(23, 5, 'LOOSE-FIT SCUBA HOODIE', 4, 'men', 1990.00, NULL, '2026-10-15 10:57:27'),
(24, 5, 'RELAXED-FIT HALF-ZIP SWEATSHIRT', 4, 'men', 1490.00, NULL, '2026-10-15 10:57:27'),
(25, 5, 'RELAXED-FIT SWEATSHIRT', 4, 'men', 899.00, NULL, '2026-10-15 10:57:27'),
(26, 5, 'RELAXED-FIT HOODIE', 4, 'men', 999.00, NULL, '2026-10-15 10:57:27'),
(27, 5, 'REGULAR-FIT POLO SHIRT', 3, 'men', 699.00, NULL, '2026-10-15 10:57:27'),
(28, 5, 'LOOSE-FIT COTTON POLO SHIRT', 3, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(29, 5, 'LOOSE-FIT CRINKLED HALF-ZIP POLO SHIRT', 3, 'men', 999.00, NULL, '2026-10-15 10:57:27'),
(30, 5, 'RELAXED-FIT POLO SHIRT', 3, 'men', 1490.00, NULL, '2026-10-15 10:57:27'),
(31, 5, 'COOLING REGULAR-FIT POLO SHIRT', 3, 'men', 899.00, NULL, '2026-10-15 10:57:27'),
(32, 5, 'FOUR-WAY STRETCH REGULAR-FIT SHIRT', 2, 'men', 1490.00, NULL, '2026-10-15 10:57:27'),
(33, 5, 'LOOSE-FIT PRINTED SHORT-SLEEVED SHIRT', 2, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(34, 5, 'OVERSHIRT', 2, 'men', 2490.00, NULL, '2026-10-15 10:57:27'),
(35, 5, 'LOOSE-FIT SHORT-SLEEVED DENIM SHIRT', 2, 'men', 1790.00, NULL, '2026-10-15 10:57:27'),
(36, 5, 'REGULAR-FIT CORDUROY SHIRT', 2, 'men', 1290.00, NULL, '2026-10-15 10:57:27'),
(37, 5, 'RELAXED-FIT T-SHIRT', 1, 'men', 499.00, NULL, '2026-10-15 10:57:27'),
(38, 5, 'REGULAR FIT T-SHIRT', 1, 'men', 399.00, NULL, '2026-10-15 10:57:27'),
(39, 5, 'OVERSIZED FIT T-SHIRT', 1, 'men', 999.00, NULL, '2026-10-15 10:57:27'),
(40, 5, 'SLIM FIT T-SHIRT', 1, 'men', 399.00, NULL, '2026-10-15 10:57:27'),
(41, 5, '3-PACK REGULAR FIT T-SHIRTS', 1, 'men', 999.00, NULL, '2026-10-15 10:57:27'),
(42, 4, 'ACG Dolomiti Gilet', 10, 'men', 5395.00, NULL, '2026-10-15 10:57:27'),
(43, 4, 'Nike Men\'s Dri-FIT 10cm (approx) Brief-Lined Running Shorts', 9, 'men', 3295.00, NULL, '2026-10-15 10:57:27'),
(44, 4, 'Paris Saint-Germain Men\'s Fleece Trousers', 6, 'men', 3195.00, NULL, '2026-10-15 10:57:27'),
(45, 4, 'Ja Men\'s Basketball Hoodie', 4, 'men', 3795.00, NULL, '2026-10-15 10:57:27'),
(46, 4, 'Jordan Sport Men\'s Golf Jacquard Polo', 3, 'men', 5695.00, NULL, '2026-10-15 10:57:27'),
(47, 4, 'Nike 24.7 PerfectStretch Men\'s Dri-FIT UV Button-Up Shirt', 2, 'men', 2937.00, NULL, '2026-10-15 10:57:27'),
(48, 4, 'Nike Club Men\'s Short-Sleeve Button-Up Shirt', 2, 'men', 2895.00, NULL, '2026-10-15 10:57:27'),
(49, 4, 'Nike Sportswear T-Shirt', 1, 'men', 1795.00, NULL, '2026-10-15 10:57:27'),
(50, 4, 'Nike Sportswear Long-Sleeve T-Shirt', 1, 'men', 2095.00, NULL, '2026-10-15 10:57:27'),
(51, 4, 'Jordan Sport Space Jam Men\'s Crew-Neck T-Shirt', 1, 'men', 2095.00, NULL, '2026-10-15 10:57:27'),
(52, 3, 'Penshoppe Play Windbreaker Jacket', 10, 'men', 1599.00, NULL, '2026-10-15 10:57:27'),
(53, 3, 'Essentials Modern Fit Sports Shorts', 9, 'men', 799.00, NULL, '2026-10-15 10:57:27'),
(54, 3, 'Slim Fit Ankle Length Chinos', 8, 'men', 899.00, NULL, '2026-10-15 10:57:27'),
(55, 3, 'Essentials Classic Straight Fit Jeans', 7, 'men', 1499.00, NULL, '2026-10-15 10:57:27'),
(56, 3, 'Dress Code Comfort Slim Fit Pants', 6, 'men', 1799.00, NULL, '2026-10-15 10:57:27'),
(57, 3, 'Dress Code Relaxed Knit Sweater', 5, 'men', 649.00, NULL, '2026-10-15 10:57:27'),
(58, 3, 'Essentials Relaxed Pullover Hoodie', 4, 'men', 1099.00, NULL, '2026-10-15 10:57:27'),
(59, 3, 'Dress Code Jacquard Polo', 3, 'men', 799.00, NULL, '2026-10-15 10:57:27'),
(60, 3, 'Boxy Shirt', 2, 'men', 899.00, NULL, '2026-10-15 10:57:27'),
(61, 3, 'Prodigy Boxy Graphic T-Shirt', 1, 'men', 549.00, NULL, '2026-10-15 10:57:27'),
(62, 4, 'Nike Sportswear Women\'s T-Shirt', 1, 'women', 1045.00, NULL, '2026-10-21 14:39:04'),
(63, 4, 'Nike Sportswear Women\'s Loose T-Shirt', 1, 'women', 1077.00, NULL, '2026-10-21 14:39:04'),
(64, 4, 'Nike Sportswear Kids\' T-Shirt', 1, 'kids', 627.00, NULL, '2026-10-21 14:39:04'),
(65, 4, 'Nike Sportswear Older Kids\' T-Shirt', 1, 'kids', 1045.00, NULL, '2026-10-21 14:39:04'),
(66, 5, 'Oxford Shirt', 2, 'women', 749.00, NULL, '2026-10-21 14:39:04'),
(67, 5, 'Knit T-Shirt', 1, 'women', 599.00, NULL, '2026-10-21 14:39:04'),
(68, 5, 'Cotton T-shirt', 1, 'kids', 299.00, NULL, '2026-10-21 14:39:04'),
(69, 5, 'Cotton Jersey T-shirt', 1, 'kids', 399.00, NULL, '2026-10-21 14:39:04'),
(70, 3, 'Low Power Mode Slim Graphic T-Shirt', 1, 'women', 499.00, NULL, '2026-10-21 14:39:04'),
(71, 3, 'Cherry Muse Slim Graphic T-Shirt', 1, 'women', 499.00, NULL, '2026-10-21 14:39:04'),
(72, 3, 'Essentials Relaxed Striped T-Shirt', 1, 'women', 499.00, NULL, '2026-10-21 14:39:04'),
(73, 3, 'Long Sleeve Striped Button-Down Top with Bows', 2, 'women', 1199.00, NULL, '2026-10-21 14:39:04');

-- --------------------------------------------------------

--
-- Table structure for table `product_colors`
--

CREATE TABLE `product_colors` (
  `product_color_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `color` varchar(80) NOT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_colors`
--

INSERT INTO `product_colors` (`product_color_id`, `product_id`, `color`, `image`) VALUES
(11, 61, 'Black', 'Penshoppe_T-Shirt_PBG_BLK.jpg'),
(12, 60, 'Red', 'Penshoppe_Shirt_BS_RED.jpg'),
(13, 59, 'Black', 'Penshoppe_Polo_DCJP_BLK.jpg'),
(14, 58, 'Evening Sand', 'Penshoppe_Hoodie_ERPH_ES.jpg'),
(15, 57, 'Midnight Navy', 'Penshoppe_Sweater_DCRKS_MN.jpg'),
(16, 56, 'Dark Gray', 'Penshoppe_Pants_DCSFP_DGY.jpg'),
(17, 55, 'Light Blue', 'Penshoppe_Jeans_ECSFJ_LBL.jpg'),
(18, 53, 'Black', 'Penshoppe_Shorts_EMFSS_BLK.jpg'),
(19, 52, 'Black', 'Penshoppe_Outerwear_PPW_BLK.jpg'),
(20, 51, 'Black', 'Nike_T-Shirts_JSSJ_BLK.jpg'),
(21, 50, 'Light Bone', 'Nike_T-Shirts_NSL_LB.jpg'),
(22, 49, 'Fir', 'Nike_T-Shirt_NSW_FIR.jpg'),
(23, 49, 'Tough Red', 'Nike_T-Shirt_NSW_TRD.jpg'),
(24, 49, 'Black', 'Nike_T-Shirt_NSW_BLK.jpg'),
(25, 49, 'White', 'Nike_T-Shirt_NSW_WHT.jpg'),
(26, 48, 'Obsidian/White', 'Nike_Shirt_NCSBSU_OW.jpg'),
(27, 47, 'Light Orewood Brown/Sail/Light Iron Ore', 'Nike_Shirt_N24DFUVBU_LOBSLIO.jpg'),
(28, 47, 'Tattoo/Black/Burgundy Crush', 'Nike_Shirt_N24DFUVBU_TBBCR.jpg'),
(29, 47, 'Black/Black/Dark Smoke Grey', 'Nike_Shirt_N24DFUVBU_BBDSG.jpg'),
(30, 46, 'Jade Smoke/Jade Horizon/Jade Smoke', 'Nike_Polo_JSMGP_JSJHJS.jpg'),
(31, 46, 'Black/Black', 'Nike_Polo_JSMGP_BLK.jpg'),
(32, 45, 'Black', 'Nike_Hoodie_JMBH_BLK.jpg'),
(33, 45, 'Burgundy Crush', 'Nike_Hoodie_JMBH_BC.jpg'),
(34, 44, 'Black', 'Nike_Pants_PSGFT_BLK.jpg'),
(35, 43, 'Space Purple/Black/Black', 'Nike_Shorts_NMDF10_SPBB.jpg'),
(36, 42, 'Black/Summit White', 'Nike_Outerwear_ACGDG_BLK.jpg'),
(37, 41, 'Black/Burgundy', 'H&M_T-Shirt_3PRF_BLKBG.jpg'),
(38, 40, 'Black', 'H&M_T-Shirt_SFT_BLK.jpg'),
(39, 39, 'Yellow/Matisse', 'H&M_T-Shirt_OFT_YMAT.jpg'),
(40, 38, 'Beige', 'H&M_T-Shirt_RFT_BEI.jpg'),
(41, 37, 'Blue', 'H&M_T-Shirt_RFXT_BLU.jpg'),
(42, 36, 'Burgundy', 'H&M_Shirt_RFCS_BURG.jpg'),
(43, 35, 'Light blue/Rerun', 'H&M_Shirt_LFSSDS_LBR.jpg'),
(44, 34, 'Dark brown', 'H&M_Shirt_OVSHT_DBR.jpg'),
(45, 33, 'Light yellow/Vibrant', 'H&M_Shirt_LFPSS_LYV.jpg'),
(46, 32, 'Navy blue', 'H&M_Shirt_FWSRFS_NVB.jpg'),
(47, 31, 'Black', 'H&M_Polo_CRFP_BLK.jpg'),
(48, 30, 'Black/Iron Maiden', 'H&M_Polo_RFP_BIM.jpg'),
(49, 29, 'Black', 'H&M_Polo_LFCHZP_BLK.jpg'),
(50, 28, 'Light gray melange/striped', 'H&M_Polo_LFCPS_LGMS.jpg'),
(51, 27, 'White', 'H&M_Polo_RFP_WHT.jpg'),
(52, 26, 'Dark gray', 'H&M_Hoodie_RFH_DGY.jpg'),
(53, 25, 'Navy blue', 'H&M_Hoodie_RFS_NVB.jpg'),
(54, 24, 'Light gray melange', 'H&M_Hoodie_RFHZS_LGM.jpg'),
(55, 23, 'Dark brown', 'H&M_Hoodie_LFSH_DBR.jpg'),
(56, 22, 'Dark blue/Las Vegas', 'H&M_Hoodie_OPH_DBLV.jpg'),
(57, 21, 'Black', 'H&M_Sweater_RFHZS_BLK.jpg'),
(58, 20, 'Dark gray melange', 'H&M_Sweater_RFC_DGM.jpg'),
(59, 19, 'Dark taupe', 'H&M_Sweater_RFFKS_DTP.jpg'),
(60, 18, 'Black', 'H&M_Sweater_RFFKTS_BLK.jpg'),
(61, 17, 'Washed dark denim gray', 'H&M_Jeans_BJ_WDDG.jpg'),
(62, 16, 'Denim black', 'H&M_Jeans_OSJ_DBK.jpg'),
(63, 15, 'Washed light denim blue', 'H&M_Jeans_UBJ_WLDB.jpg'),
(64, 14, 'Light denim blue', 'H&M_Jeans_RJ_LDB.jpg'),
(65, 13, 'Navy blue', 'H&M_Chinos_RFCTC_NVB.jpg'),
(66, 12, 'Dark brown', 'H&M_Chinos_RFC_DBR.jpg'),
(67, 11, 'Light gray melange', 'H&M_Shorts_RFS_LGM.jpg'),
(68, 10, 'Khaki green', 'H&M_Shorts_LFCCS_KHG.jpg'),
(69, 9, 'Denim black', 'H&M_Shorts_RFDS_DBK.jpg'),
(70, 8, 'White', 'H&M_Shorts_RFLBS_WHT.jpg'),
(71, 7, 'Denim gray', 'H&M_Shorts_BDS_DG.jpg'),
(72, 6, 'Khaki green', 'H&M_Outerwear_WWRJ_KHG.jpg'),
(73, 54, 'Black', 'Penshoppe_Chinos_SFALC_BEI.jpg'),
(74, 50, 'Anthracite', 'Nike_T-Shirt_NSL_ANTH.jpg'),
(75, 49, 'Old Royal', NULL),
(76, 48, 'Photon Dust/White', NULL),
(77, 62, 'Black', 'Nike_T-Shirt_NS_BLK.jpg'),
(78, 62, 'White', 'Nike_T-Shirt_NS_WHT.jpg'),
(79, 63, 'Black', 'Nike_T-Shirt_NSLT_BLK.jpg'),
(80, 64, 'White', 'Nike_T-Shirt_NSK_WHT.jpg'),
(81, 65, 'Black', 'Nike_T-Shirt_NSOK_BLK.jpg'),
(82, 65, 'White', 'Nike_T-Shirt_NSOK_WHT.jpg'),
(83, 66, 'White', 'H&M_Shirt_OS_WHT.jpg'),
(84, 66, 'Blue', 'H&M_Shirt_OS_BLUE.jpg'),
(85, 66, 'Light Pink', 'H&M_Shirt_OS_LP.jpg'),
(86, 67, 'Beige melange', 'H&M_T-Shirt_KTS_BM.jpg'),
(87, 67, 'Cream', 'H&M_T-Shirt_KTS_CREAM.jpg'),
(88, 68, 'White', 'H&M_T-Shirt_CTS_WHT.jpg'),
(89, 68, 'Light Red', 'H&M_T-Shirt_CTS_LR.jpg'),
(90, 68, 'Light Turqoise', 'H&M_T-Shirt_CTS_LT.jpg'),
(91, 69, 'Cerise', 'H&M_T-Shirt_CJTS_CERISE.jpg'),
(92, 70, 'Off White', 'Penshoppe_T-Shirt_LPMSGTS_OW.jpg'),
(93, 71, 'Black', 'Penshoppe_T-Shirt_CMSGTS_BLK.jpg'),
(94, 72, 'Red', 'Penshoppe_T-Shirt_ERSTS_RED.jpg'),
(95, 73, 'Blush', 'Penshoppe_Shirt_LSSBDTB_BLU.jpg'),
(98, 43, 'Space Purple', 'Nike_Shorts_NMDF10_SPBB.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `product_color_images`
--

CREATE TABLE `product_color_images` (
  `image_id` int(11) NOT NULL,
  `product_color_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_color_images`
--

INSERT INTO `product_color_images` (`image_id`, `product_color_id`, `image_path`, `sort_order`, `created_at`) VALUES
(1, 11, 'Penshoppe_T-Shirt_PBG_BLK.jpg', 0, '2026-10-26 08:47:39'),
(2, 12, 'Penshoppe_Shirt_BS_RED.jpg', 0, '2026-10-26 08:47:39'),
(3, 13, 'Penshoppe_Polo_DCJP_BLK.jpg', 0, '2026-10-26 08:47:39'),
(4, 14, 'Penshoppe_Hoodie_ERPH_ES.jpg', 0, '2026-10-26 08:47:39'),
(5, 15, 'Penshoppe_Sweater_DCRKS_MN.jpg', 0, '2026-10-26 08:47:39'),
(6, 16, 'Penshoppe_Pants_DCSFP_DGY.jpg', 0, '2026-10-26 08:47:39'),
(7, 17, 'Penshoppe_Jeans_ECSFJ_LBL.jpg', 0, '2026-10-26 08:47:39'),
(8, 18, 'Penshoppe_Shorts_EMFSS_BLK.jpg', 0, '2026-10-26 08:47:39'),
(9, 19, 'Penshoppe_Outerwear_PPW_BLK.jpg', 0, '2026-10-26 08:47:39'),
(10, 20, 'Nike_T-Shirts_JSSJ_BLK.jpg', 0, '2026-10-26 08:47:39'),
(11, 21, 'Nike_T-Shirts_NSL_LB.jpg', 0, '2026-10-26 08:47:39'),
(12, 22, 'Nike_T-Shirt_NSW_FIR.jpg', 0, '2026-10-26 08:47:39'),
(13, 23, 'Nike_T-Shirt_NSW_TRD.jpg', 0, '2026-10-26 08:47:39'),
(14, 24, 'Nike_T-Shirt_NSW_BLK.jpg', 0, '2026-10-26 08:47:39'),
(15, 25, 'Nike_T-Shirt_NSW_WHT.jpg', 0, '2026-10-26 08:47:39'),
(16, 26, 'Nike_Shirt_NCSBSU_OW.jpg', 0, '2026-10-26 08:47:39'),
(17, 27, 'Nike_Shirt_N24DFUVBU_LOBSLIO.jpg', 0, '2026-10-26 08:47:39'),
(18, 28, 'Nike_Shirt_N24DFUVBU_TBBCR.jpg', 0, '2026-10-26 08:47:39'),
(19, 29, 'Nike_Shirt_N24DFUVBU_BBDSG.jpg', 0, '2026-10-26 08:47:39'),
(20, 30, 'Nike_Polo_JSMGP_JSJHJS.jpg', 0, '2026-10-26 08:47:39'),
(21, 31, 'Nike_Polo_JSMGP_BLK.jpg', 0, '2026-10-26 08:47:39'),
(22, 32, 'Nike_Hoodie_JMBH_BLK.jpg', 0, '2026-10-26 08:47:39'),
(23, 33, 'Nike_Hoodie_JMBH_BC.jpg', 0, '2026-10-26 08:47:39'),
(24, 34, 'Nike_Pants_PSGFT_BLK.jpg', 0, '2026-10-26 08:47:39'),
(25, 35, 'Nike_Shorts_NMDF10_SPBB.jpg', 0, '2026-10-26 08:47:39'),
(26, 36, 'Nike_Outerwear_ACGDG_BLK.jpg', 0, '2026-10-26 08:47:39'),
(27, 37, 'H&M_T-Shirt_3PRF_BLKBG.jpg', 0, '2026-10-26 08:47:39'),
(28, 38, 'H&M_T-Shirt_SFT_BLK.jpg', 0, '2026-10-26 08:47:39'),
(29, 39, 'H&M_T-Shirt_OFT_YMAT.jpg', 0, '2026-10-26 08:47:39'),
(30, 40, 'H&M_T-Shirt_RFT_BEI.jpg', 0, '2026-10-26 08:47:39'),
(31, 41, 'H&M_T-Shirt_RFXT_BLU.jpg', 0, '2026-10-26 08:47:39'),
(32, 42, 'H&M_Shirt_RFCS_BURG.jpg', 0, '2026-10-26 08:47:39'),
(33, 43, 'H&M_Shirt_LFSSDS_LBR.jpg', 0, '2026-10-26 08:47:39'),
(34, 44, 'H&M_Shirt_OVSHT_DBR.jpg', 0, '2026-10-26 08:47:39'),
(35, 45, 'H&M_Shirt_LFPSS_LYV.jpg', 0, '2026-10-26 08:47:39'),
(36, 46, 'H&M_Shirt_FWSRFS_NVB.jpg', 0, '2026-10-26 08:47:39'),
(37, 47, 'H&M_Polo_CRFP_BLK.jpg', 0, '2026-10-26 08:47:39'),
(38, 48, 'H&M_Polo_RFP_BIM.jpg', 0, '2026-10-26 08:47:39'),
(39, 49, 'H&M_Polo_LFCHZP_BLK.jpg', 0, '2026-10-26 08:47:39'),
(40, 50, 'H&M_Polo_LFCPS_LGMS.jpg', 0, '2026-10-26 08:47:39'),
(41, 51, 'H&M_Polo_RFP_WHT.jpg', 0, '2026-10-26 08:47:39'),
(42, 52, 'H&M_Hoodie_RFH_DGY.jpg', 0, '2026-10-26 08:47:39'),
(43, 53, 'H&M_Hoodie_RFS_NVB.jpg', 0, '2026-10-26 08:47:39'),
(44, 54, 'H&M_Hoodie_RFHZS_LGM.jpg', 0, '2026-10-26 08:47:39'),
(45, 55, 'H&M_Hoodie_LFSH_DBR.jpg', 0, '2026-10-26 08:47:39'),
(46, 56, 'H&M_Hoodie_OPH_DBLV.jpg', 0, '2026-10-26 08:47:39'),
(47, 57, 'H&M_Sweater_RFHZS_BLK.jpg', 0, '2026-10-26 08:47:39'),
(48, 58, 'H&M_Sweater_RFC_DGM.jpg', 0, '2026-10-26 08:47:39'),
(49, 59, 'H&M_Sweater_RFFKS_DTP.jpg', 0, '2026-10-26 08:47:39'),
(50, 60, 'H&M_Sweater_RFFKTS_BLK.jpg', 0, '2026-10-26 08:47:39'),
(51, 61, 'H&M_Jeans_BJ_WDDG.jpg', 0, '2026-10-26 08:47:39'),
(52, 62, 'H&M_Jeans_OSJ_DBK.jpg', 0, '2026-10-26 08:47:39'),
(53, 63, 'H&M_Jeans_UBJ_WLDB.jpg', 0, '2026-10-26 08:47:39'),
(54, 64, 'H&M_Jeans_RJ_LDB.jpg', 0, '2026-10-26 08:47:39'),
(55, 65, 'H&M_Chinos_RFCTC_NVB.jpg', 0, '2026-10-26 08:47:39'),
(56, 66, 'H&M_Chinos_RFC_DBR.jpg', 0, '2026-10-26 08:47:39'),
(57, 67, 'H&M_Shorts_RFS_LGM.jpg', 0, '2026-10-26 08:47:39'),
(58, 68, 'H&M_Shorts_LFCCS_KHG.jpg', 0, '2026-10-26 08:47:39'),
(59, 69, 'H&M_Shorts_RFDS_DBK.jpg', 0, '2026-10-26 08:47:39'),
(60, 70, 'H&M_Shorts_RFLBS_WHT.jpg', 0, '2026-10-26 08:47:39'),
(61, 71, 'H&M_Shorts_BDS_DG.jpg', 0, '2026-10-26 08:47:39'),
(62, 72, 'H&M_Outerwear_WWRJ_KHG.jpg', 0, '2026-10-26 08:47:39'),
(63, 73, 'Penshoppe_Chinos_SFALC_BEI.jpg', 0, '2026-10-26 08:47:39'),
(64, 74, 'Nike_T-Shirt_NSL_ANTH.jpg', 0, '2026-10-26 08:47:39'),
(65, 77, 'Nike_T-Shirt_NS_BLK.jpg', 0, '2026-10-26 08:47:39'),
(66, 78, 'NIKE_T-Shirt_NS_WHT.jpg', 0, '2026-10-26 08:47:39'),
(67, 79, 'Nike_T-Shirt_NSLT_BLK.jpg', 0, '2026-10-26 08:47:39'),
(68, 80, 'Nike_T-Shirt_NSK_WHT.jpg', 0, '2026-10-26 08:47:39'),
(69, 81, 'Nike_T-Shirt_NSOK_BLK.jpg', 0, '2026-10-26 08:47:39'),
(70, 82, 'Nike_T-Shirt_NSOK_WHT.jpg', 0, '2026-10-26 08:47:39'),
(71, 83, 'H&M_Shirt_OS_WHT.jpg', 0, '2026-10-26 08:47:39'),
(72, 84, 'H&M_Shirt_OS_BLUE.jpg', 0, '2026-10-26 08:47:39'),
(73, 85, 'H&M_Shirt_OS_LP.jpg', 0, '2026-10-26 08:47:39'),
(74, 86, 'H&M_T-Shirt_KTS_BM.jpg', 0, '2026-10-26 08:47:39'),
(75, 87, 'H&M_T-Shirt_KTS_CREAM.jpg', 0, '2026-10-26 08:47:39'),
(76, 88, 'H&M_T-Shirt_CTS_WHT.jpg', 0, '2026-10-26 08:47:39'),
(77, 89, 'H&M_T-Shirt_CTS_LR.jpg', 0, '2026-10-26 08:47:39'),
(78, 90, 'H&M_T-Shirt_CTS_LT.jpg', 0, '2026-10-26 08:47:39'),
(79, 91, 'H&M_T-Shirt_CJTS_CERISE.jpg', 0, '2026-10-26 08:47:39'),
(80, 92, 'Penshoppe_T-Shirt_LPMSGTS_OW.jpg', 0, '2026-10-26 08:47:39'),
(81, 93, 'Penshoppe_T-Shirt_CMSGTS_BLK.jpg', 0, '2026-10-26 08:47:39'),
(82, 94, 'Penshoppe_T-Shirt_ERSTS_RED.jpg', 0, '2026-10-26 08:47:39'),
(83, 95, 'Penshoppe_Shirt_LSSBDTB_BLU.jpg', 0, '2026-10-26 08:47:39');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `review_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `rating` tinyint(3) UNSIGNED NOT NULL,
  `review_text` text NOT NULL,
  `status` enum('pending','published','deleted') NOT NULL DEFAULT 'published',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `variant_id` int(11) NOT NULL,
  `product_color_id` int(11) NOT NULL,
  `size` varchar(20) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`variant_id`, `product_color_id`, `size`, `stock`) VALUES
(31, 11, 'XS', 5),
(32, 11, 'S', 5),
(33, 11, 'M', 5),
(34, 11, 'L', 5),
(35, 11, 'XL', 5),
(36, 12, 'XS', 5),
(37, 12, 'S', 5),
(38, 12, 'M', 5),
(39, 12, 'L', 5),
(40, 12, 'XL', 5),
(41, 13, 'XS', 5),
(42, 13, 'S', 5),
(43, 13, 'M', 5),
(44, 13, 'L', 5),
(45, 13, 'XL', 5),
(46, 14, 'XS', 5),
(47, 15, 'L', 5),
(48, 16, 'XS', 5),
(50, 73, 'XS', 5),
(51, 18, 'XS', 5),
(52, 19, 'XS', 5),
(53, 19, 'S', 5),
(54, 37, 'XS', 5),
(55, 37, 'S', 5),
(56, 37, 'M', 5),
(57, 37, 'L', 5),
(58, 37, 'XL', 5),
(59, 37, 'XXL', 5),
(60, 38, 'XS/L', 5),
(61, 38, 'S/L', 5),
(62, 38, 'M/L', 5),
(63, 38, 'L/L', 5),
(64, 38, 'XL/L', 5),
(65, 38, 'XXL/L', 5),
(66, 38, '3XL/L', 5),
(67, 38, 'XS', 5),
(68, 38, 'S', 5),
(69, 38, 'M', 5),
(70, 38, 'L', 5),
(71, 38, 'XL', 5),
(72, 38, 'XXL', 5),
(73, 39, 'XS', 5),
(74, 39, 'S', 5),
(75, 39, 'M', 5),
(76, 39, 'L', 5),
(77, 39, 'XL', 5),
(78, 39, 'XXL', 5),
(79, 40, 'XS', 5),
(80, 40, 'S', 5),
(81, 40, 'M', 5),
(82, 40, 'L', 5),
(83, 40, 'XL', 5),
(84, 40, 'XXL', 5),
(85, 41, 'XS', 5),
(86, 41, 'S', 5),
(87, 41, 'M', 5),
(88, 41, 'L', 5),
(89, 41, 'XL', 5),
(90, 41, 'XXL', 5),
(91, 42, 'XS', 5),
(92, 42, 'S', 5),
(93, 42, 'M', 5),
(94, 42, 'L', 5),
(95, 42, 'XL', 5),
(96, 42, 'XXL', 5),
(97, 43, 'XS', 5),
(98, 43, 'S', 5),
(99, 43, 'M', 5),
(100, 43, 'L', 5),
(101, 43, 'XL', 5),
(102, 45, 'XS', 5),
(103, 45, 'S', 5),
(104, 45, 'M', 5),
(105, 45, 'L', 5),
(106, 45, 'XL', 5),
(107, 46, 'S', 5),
(108, 46, 'M', 5),
(109, 46, 'L', 5),
(110, 46, 'XL', 5),
(111, 48, 'XS', 5),
(112, 48, 'S', 5),
(113, 48, 'M', 5),
(114, 48, 'L', 5),
(115, 48, 'XL', 5),
(116, 49, 'XS', 5),
(117, 49, 'S', 5),
(118, 49, 'M', 5),
(119, 49, 'L', 5),
(120, 49, 'XL', 5),
(121, 50, 'XS', 5),
(122, 50, 'S', 5),
(123, 50, 'M', 5),
(124, 50, 'L', 5),
(125, 50, 'XL', 5),
(126, 51, 'XS', 5),
(127, 51, 'S', 5),
(128, 51, 'M', 5),
(129, 51, 'L', 5),
(130, 51, 'XL', 5),
(131, 51, 'XXL', 5),
(132, 52, 'XS', 5),
(133, 52, 'S', 5),
(134, 52, 'M', 5),
(135, 52, 'L', 5),
(136, 52, 'XL', 5),
(137, 52, 'XXL', 5),
(138, 53, 'XS', 5),
(139, 53, 'S', 5),
(140, 53, 'M', 5),
(141, 53, 'L', 5),
(142, 53, 'XL', 5),
(143, 53, 'XXL', 5),
(144, 55, 'S', 5),
(145, 55, 'M', 5),
(146, 55, 'L', 5),
(147, 55, 'XL', 5),
(148, 55, 'XXL', 5),
(149, 60, 'XS', 5),
(150, 60, 'S', 5),
(151, 60, 'M', 5),
(152, 60, 'L', 5),
(153, 60, 'XL', 5),
(154, 60, 'XXL', 5),
(155, 64, 'S', 5),
(156, 67, 'XS', 5),
(157, 67, 'S', 5),
(158, 67, 'M', 5),
(159, 67, 'L', 5),
(160, 67, 'XL', 5),
(161, 67, 'XXL', 5),
(162, 72, 'XS', 5),
(163, 72, 'S', 5),
(164, 72, 'M', 5),
(165, 72, 'L', 5),
(166, 72, 'XL', 5),
(173, 21, 'S', 5),
(174, 74, 'S', 5),
(175, 21, 'M', 5),
(176, 74, 'M', 5),
(177, 21, 'L', 5),
(178, 74, 'L', 5),
(179, 21, 'XL', 5),
(180, 74, 'XL', 5),
(181, 21, '2XL', 5),
(182, 74, '2XL', 5),
(183, 22, 'S', 5),
(184, 75, 'S', 5),
(185, 23, 'S', 5),
(186, 24, 'S', 5),
(187, 25, 'S', 5),
(188, 22, 'M', 5),
(189, 75, 'M', 5),
(190, 23, 'M', 5),
(191, 24, 'M', 5),
(192, 25, 'M', 5),
(193, 22, 'L', 5),
(194, 75, 'L', 5),
(195, 23, 'L', 5),
(196, 24, 'L', 5),
(197, 25, 'L', 5),
(198, 22, 'XL', 5),
(199, 75, 'XL', 5),
(200, 23, 'XL', 5),
(201, 24, 'XL', 5),
(202, 25, 'XL', 5),
(203, 22, '2XL', 5),
(204, 75, '2XL', 5),
(205, 23, '2XL', 5),
(206, 24, '2XL', 5),
(207, 25, '2XL', 5),
(208, 26, 'XS', 5),
(209, 76, 'XS', 5),
(210, 26, 'S', 5),
(211, 76, 'S', 5),
(212, 26, 'M', 5),
(213, 76, 'M', 5),
(214, 26, 'L', 5),
(215, 76, 'L', 5),
(216, 26, 'XL', 5),
(217, 76, 'XL', 5),
(218, 26, '2XL', 5),
(219, 76, '2XL', 5),
(220, 27, 'S', 5),
(221, 28, 'S', 5),
(222, 29, 'S', 5),
(223, 27, 'M', 5),
(224, 28, 'M', 5),
(225, 29, 'M', 5),
(226, 27, 'L', 5),
(227, 28, 'L', 5),
(228, 29, 'L', 5),
(229, 27, 'XL', 5),
(230, 28, 'XL', 5),
(231, 29, 'XL', 5),
(232, 27, '2XL', 5),
(233, 28, '2XL', 5),
(234, 29, '2XL', 5),
(246, 31, 'XXL', 10),
(247, 32, 'S', 5),
(248, 33, 'S', 5),
(249, 32, 'M', 5),
(250, 33, 'M', 5),
(251, 32, 'L', 5),
(252, 33, 'L', 5),
(253, 32, 'XL', 5),
(254, 33, 'XL', 5),
(255, 32, '2XL', 5),
(256, 33, '2XL', 5),
(262, 35, 'S', 10),
(263, 35, 'M', 10),
(264, 35, 'L', 10),
(265, 35, 'XL', 10),
(266, 35, '2XL', 10),
(267, 36, 'S', 5),
(268, 36, 'M', 5),
(269, 36, 'L', 5),
(270, 36, 'XL', 5),
(271, 36, '2XL', 5),
(287, 77, 'XS', 5),
(288, 78, 'XS', 5),
(289, 79, 'XS', 5),
(290, 80, 'XS', 5),
(291, 81, 'XS', 5),
(292, 82, 'XS', 5),
(293, 83, 'XS', 5),
(294, 84, 'XS', 5),
(295, 85, 'XS', 5),
(296, 86, 'XXS', 5),
(297, 87, 'XXS', 5),
(298, 88, 'XS', 5),
(299, 89, 'XS', 5),
(300, 90, 'XS', 5),
(301, 91, 'S', 5),
(302, 92, 'S', 5),
(303, 93, 'S', 5),
(304, 94, 'S', 5),
(305, 95, 'S', 5),
(306, 77, 'S', 5),
(307, 78, 'S', 5),
(308, 79, 'S', 5),
(309, 80, 'S', 5),
(310, 81, 'S', 5),
(311, 82, 'S', 5),
(312, 83, 'S', 5),
(313, 84, 'S', 5),
(314, 85, 'S', 5),
(315, 86, 'XS', 5),
(316, 87, 'XS', 5),
(317, 88, 'S', 5),
(318, 89, 'S', 5),
(319, 90, 'S', 5),
(320, 91, 'M', 5),
(321, 92, 'M', 5),
(322, 93, 'M', 5),
(323, 94, 'M', 5),
(324, 95, 'M', 5),
(325, 77, 'M', 5),
(326, 78, 'M', 5),
(327, 79, 'M', 5),
(328, 80, 'M', 5),
(329, 81, 'M', 5),
(330, 82, 'M', 5),
(331, 83, 'M', 5),
(332, 84, 'M', 5),
(333, 85, 'M', 5),
(334, 86, 'S', 5),
(335, 87, 'S', 5),
(336, 88, 'M', 5),
(337, 89, 'M', 5),
(338, 90, 'M', 5),
(339, 91, 'L', 5),
(340, 92, 'L', 5),
(341, 93, 'L', 5),
(342, 94, 'L', 5),
(343, 95, 'L', 5),
(344, 77, 'L', 5),
(345, 78, 'L', 5),
(346, 79, 'L', 5),
(347, 80, 'L', 5),
(348, 81, 'L', 5),
(349, 82, 'L', 5),
(350, 83, 'L', 5),
(351, 84, 'L', 5),
(352, 85, 'L', 5),
(353, 86, 'M', 5),
(354, 87, 'M', 5),
(355, 88, 'L', 5),
(356, 89, 'L', 5),
(357, 90, 'L', 5),
(358, 91, 'XL', 5),
(359, 92, 'XL', 5),
(360, 93, 'XL', 5),
(361, 94, 'XL', 5),
(362, 95, 'XL', 5),
(363, 77, 'XL', 5),
(364, 78, 'XL', 5),
(365, 79, 'XL', 5),
(366, 80, 'XL', 5),
(367, 81, 'XL', 5),
(368, 82, 'XL', 5),
(369, 83, 'XL', 5),
(370, 84, 'XL', 5),
(371, 85, 'XL', 5),
(372, 86, 'L', 5),
(373, 87, 'L', 5),
(374, 88, 'XL', 5),
(375, 89, 'XL', 5),
(376, 90, 'XL', 5),
(377, 92, 'XXL', 5),
(378, 93, 'XXL', 5),
(379, 94, 'XXL', 5),
(380, 95, 'XXL', 5),
(381, 77, '2XL', 5),
(382, 78, '2XL', 5),
(383, 86, 'XL', 5),
(384, 87, 'XL', 5),
(385, 86, 'XXL', 5),
(386, 87, 'XXL', 5),
(391, 71, 'XS', 5),
(392, 71, 'S', 5),
(393, 71, 'M', 5),
(394, 71, 'L', 5),
(395, 71, 'XL', 5),
(396, 71, 'XXL', 5),
(397, 61, 'XS', 5),
(398, 61, 'S', 5),
(399, 61, 'M', 5),
(400, 61, 'L', 5),
(401, 61, 'XL', 5),
(402, 61, 'XXL', 5),
(403, 68, 'XS', 5),
(404, 68, 'S', 5),
(405, 68, 'M', 5),
(406, 68, 'L', 5),
(407, 68, 'XL', 5),
(408, 68, 'XXL', 5),
(409, 62, 'XS', 5),
(410, 62, 'S', 5),
(411, 62, 'M', 5),
(412, 62, 'L', 5),
(413, 62, 'XL', 5),
(414, 62, 'XXL', 5),
(415, 44, 'XS', 5),
(416, 44, 'S', 5),
(417, 44, 'M', 5),
(418, 44, 'L', 5),
(419, 44, 'XL', 5),
(420, 44, 'XXL', 5),
(421, 56, 'XS', 5),
(422, 56, 'S', 5),
(423, 56, 'M', 5),
(424, 56, 'L', 5),
(425, 56, 'XL', 5),
(426, 56, 'XXL', 5),
(427, 58, 'XS', 5),
(428, 58, 'S', 5),
(429, 58, 'M', 5),
(430, 58, 'L', 5),
(431, 58, 'XL', 5),
(432, 58, 'XXL', 5),
(433, 59, 'XS', 5),
(434, 59, 'S', 5),
(435, 59, 'M', 5),
(436, 59, 'L', 5),
(437, 59, 'XL', 5),
(438, 59, 'XXL', 5),
(439, 57, 'XS', 5),
(440, 57, 'S', 5),
(441, 57, 'M', 5),
(442, 57, 'L', 5),
(443, 57, 'XL', 5),
(444, 57, 'XXL', 5),
(445, 70, 'XS', 5),
(446, 70, 'S', 5),
(447, 70, 'M', 5),
(448, 70, 'L', 5),
(449, 70, 'XL', 5),
(450, 70, 'XXL', 5),
(451, 66, 'XS', 5),
(452, 66, 'S', 5),
(453, 66, 'M', 5),
(454, 66, 'L', 5),
(455, 66, 'XL', 5),
(456, 66, 'XXL', 5),
(457, 65, 'XS', 5),
(458, 65, 'S', 5),
(459, 65, 'M', 5),
(460, 65, 'L', 5),
(461, 65, 'XL', 5),
(462, 65, 'XXL', 5),
(463, 69, 'XS', 5),
(464, 69, 'S', 5),
(465, 69, 'M', 5),
(466, 69, 'L', 5),
(467, 69, 'XL', 5),
(468, 69, 'XXL', 5),
(469, 54, 'XS', 5),
(470, 54, 'S', 5),
(471, 54, 'M', 5),
(472, 54, 'L', 5),
(473, 54, 'XL', 5),
(474, 54, 'XXL', 5),
(475, 63, 'XS', 5),
(476, 63, 'S', 5),
(477, 63, 'M', 5),
(478, 63, 'L', 5),
(479, 63, 'XL', 5),
(480, 63, 'XXL', 5),
(481, 31, 'XS', 15),
(482, 31, 'S', 15),
(483, 31, 'M', 15),
(484, 31, 'L', 15),
(485, 31, 'XL', 15),
(486, 31, '2XL', 5),
(518, 17, 'XXS', 15),
(519, 47, 'XS', 5),
(520, 47, 'S', 5),
(521, 47, 'M', 5),
(522, 47, 'L', 5),
(523, 47, 'XL', 5),
(524, 47, 'XXL', 5),
(525, 20, 'XS', 15),
(526, 20, 'S', 15),
(527, 20, 'M', 15),
(528, 20, 'L', 15),
(529, 20, 'XL', 15),
(530, 20, 'XXL', 15),
(531, 30, 'XS', 15),
(532, 30, 'S', 15),
(533, 30, 'M', 15),
(534, 30, 'L', 15),
(535, 30, 'XL', 15),
(536, 30, 'XXL', 15),
(537, 34, 'S', 15),
(538, 34, 'M', 15),
(539, 34, 'L', 15),
(540, 34, 'XL', 15),
(541, 34, 'XXL', 15),
(542, 98, 'S', 5),
(543, 98, 'M', 5),
(544, 98, 'L', 5),
(545, 98, 'XL', 5),
(546, 98, '2XL', 5);

-- --------------------------------------------------------

--
-- Table structure for table `restocks`
--

CREATE TABLE `restocks` (
  `restock_id` int(11) NOT NULL,
  `batch_number` varchar(20) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `restock_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restock_items`
--

CREATE TABLE `restock_items` (
  `restock_item_id` int(11) NOT NULL,
  `restock_id` int(11) NOT NULL,
  `variant_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `restock_totals`
-- (See below for the actual view)
--
CREATE TABLE `restock_totals` (
`restock_id` int(11)
,`total` decimal(42,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `name`, `image`) VALUES
(3, 'PENSHOPPE', NULL),
(4, 'Nike', NULL),
(5, 'H&M', 'h-m-logo.png');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('customer','admin','vip') NOT NULL DEFAULT 'customer',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `vip_expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `role`, `status`, `vip_expires_at`, `created_at`) VALUES
(1, 'admin', 'admin@soigne.test', '$2b$10$V9o3eRnHsI4prmOLfNGmBe/ld7ISyKmZCn7eTO5kxh1LMr5yjc16e', 'admin', 'active', NULL, '2026-10-07 13:51:30'),
(2, 'juandc', 'juan@example.com', '$2b$10$Q2EiBPqJDIdS4WoAESA29umbyGe1ecsDKXmlGQSC9QA03223OIZ6C', 'customer', 'active', NULL, '2026-10-07 13:51:30'),
(4, 'rexter', 'briane09@gmail.com', '$2y$10$2TxmgRtpgoaEVoamPbBJDu.R76thdMkSBZBGnIGEoxP6xDG/kYaiG', 'customer', 'active', NULL, '2026-10-08 10:57:59');

-- --------------------------------------------------------

--
-- Structure for view `all_costs`
--
DROP TABLE IF EXISTS `all_costs`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `all_costs`  AS SELECT date_format(`r`.`restock_date`,'%Y-%m-01') AS `cost_month`, 'restock' AS `category`, `t`.`total` AS `amount` FROM (`restocks` `r` join `restock_totals` `t` on(`t`.`restock_id` = `r`.`restock_id`))union all select `expenses`.`billing_month` AS `billing_month`,`expenses`.`category` AS `category`,`expenses`.`amount` AS `amount` from `expenses`  ;

-- --------------------------------------------------------

--
-- Structure for view `order_totals`
--
DROP TABLE IF EXISTS `order_totals`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `order_totals`  AS SELECT `order_items`.`order_id` AS `order_id`, sum(`order_items`.`quantity` * `order_items`.`price`) AS `total` FROM `order_items` GROUP BY `order_items`.`order_id` ;

-- --------------------------------------------------------

--
-- Structure for view `restock_totals`
--
DROP TABLE IF EXISTS `restock_totals`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `restock_totals`  AS SELECT `restock_items`.`restock_id` AS `restock_id`, sum(`restock_items`.`quantity` * `restock_items`.`unit_cost`) AS `total` FROM `restock_items` GROUP BY `restock_items`.`restock_id` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`cart_id`),
  ADD UNIQUE KEY `uniq_cart_item` (`user_id`,`variant_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD UNIQUE KEY `uniq_category_month` (`category`,`billing_month`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD UNIQUE KEY `uniq_order_item` (`order_id`,`variant_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `premium_payments`
--
ALTER TABLE `premium_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_premium_payments_user_status` (`user_id`,`status`),
  ADD KEY `idx_premium_payments_status_submitted` (`status`,`submitted_at`),
  ADD KEY `reviewed_by` (`reviewed_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `idx_department` (`department`),
  ADD KEY `idx_products_category_id` (`category_id`);

--
-- Indexes for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD PRIMARY KEY (`product_color_id`),
  ADD UNIQUE KEY `uniq_product_color` (`product_id`,`color`);

--
-- Indexes for table `product_color_images`
--
ALTER TABLE `product_color_images`
  ADD PRIMARY KEY (`image_id`),
  ADD UNIQUE KEY `uniq_product_color_image` (`product_color_id`,`image_path`),
  ADD KEY `idx_product_color_images_order` (`product_color_id`,`sort_order`,`image_id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uniq_product_review_user` (`product_id`,`user_id`),
  ADD KEY `idx_product_reviews_order_item` (`order_item_id`),
  ADD KEY `idx_product_reviews_display` (`product_id`,`status`,`created_at`),
  ADD KEY `idx_product_reviews_moderation` (`status`,`created_at`),
  ADD KEY `fk_product_reviews_user` (`user_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`variant_id`),
  ADD UNIQUE KEY `uniq_variant` (`product_color_id`,`size`);

--
-- Indexes for table `restocks`
--
ALTER TABLE `restocks`
  ADD PRIMARY KEY (`restock_id`),
  ADD UNIQUE KEY `batch_number` (`batch_number`),
  ADD KEY `supplier_id` (`supplier_id`);

--
-- Indexes for table `restock_items`
--
ALTER TABLE `restock_items`
  ADD PRIMARY KEY (`restock_item_id`),
  ADD UNIQUE KEY `uniq_restock_item` (`restock_id`,`variant_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `cart_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `premium_payments`
--
ALTER TABLE `premium_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `product_colors`
--
ALTER TABLE `product_colors`
  MODIFY `product_color_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `product_color_images`
--
ALTER TABLE `product_color_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=128;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `variant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=549;

--
-- AUTO_INCREMENT for table `restocks`
--
ALTER TABLE `restocks`
  MODIFY `restock_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `restock_items`
--
ALTER TABLE `restock_items`
  MODIFY `restock_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`);

--
-- Constraints for table `premium_payments`
--
ALTER TABLE `premium_payments`
  ADD CONSTRAINT `premium_payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `premium_payments_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `product_colors`
--
ALTER TABLE `product_colors`
  ADD CONSTRAINT `product_colors_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`);

--
-- Constraints for table `product_color_images`
--
ALTER TABLE `product_color_images`
  ADD CONSTRAINT `fk_product_color_images_color` FOREIGN KEY (`product_color_id`) REFERENCES `product_colors` (`product_color_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_product_reviews_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_color_id`) REFERENCES `product_colors` (`product_color_id`);

--
-- Constraints for table `restocks`
--
ALTER TABLE `restocks`
  ADD CONSTRAINT `restocks_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`);

--
-- Constraints for table `restock_items`
--
ALTER TABLE `restock_items`
  ADD CONSTRAINT `restock_items_ibfk_1` FOREIGN KEY (`restock_id`) REFERENCES `restocks` (`restock_id`),
  ADD CONSTRAINT `restock_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`variant_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
