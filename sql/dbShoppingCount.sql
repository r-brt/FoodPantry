-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 26, 2026 at 09:09 PM
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
-- Database: `foodpantrydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `dbshoppingcounts`
--

DROP TABLE IF EXISTS `dbshoppingcounts`;
-- NOTE: After importing this file also run dbShoppingCountGroup.sql
-- which adds the groupId and notes columns.
CREATE TABLE `dbshoppingcounts` (
  `id` int(11) NOT NULL,
  `shoppingEventId` int(11) NOT NULL,
  `itemCategoryId` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `groupId` int(11) DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

--
-- Dumping data for table `dbshoppingcounts`
--

INSERT INTO `dbshoppingcounts` (`id`, `shoppingEventId`, `itemCategoryId`, `quantity`) VALUES
-- shoppingEventId references dbshoppingevent.id
-- 1001 = 1-3
-- 1002 = 1-3-IH
-- 1003 = 4+
-- 1004 = 4+-IH
-- 1005 = 8+-IH

-- 1-3 family size shopping event

(1, 1001, 6, 1), -- chicken
(2, 1001, 25, 2), -- tuna
(3, 1001, 23, 2), -- tomato sauce canned
(4, 1001, 21, 1), -- spaghetti
(5, 1001, 2, 2), -- Beans Canned
(6, 1001, 3, 1), -- Beans Dry
(7, 1001, 9, 2), -- Green Beans
(8, 1001, 7, 2), -- Corn
(9, 1001, 12, 1), -- Mixed Veg
(10, 1001, 20, 2), -- Soup
(11, 1001, 8, 1), -- Fruit
(12, 1001, 16, 2), -- Pasta
(13, 1001, 11, 4), -- Mac and Cheese
(14, 1001, 18, 4), -- Ramen
(15, 1001, 19, 1), -- Snacks
(16, 1001, 5, 1), -- Cereal
(17, 1001, 15, 1), -- Pancake
(18, 1001, 4, 1), -- Canned Meals

-- 1-3 IH family size shopping event

(19, 1002, 6, 1), -- chicken
(20, 1002, 25, 2), -- tuna
(21, 1002, 23, 2), -- tomato sauce canned
(22, 1002, 21, 1), -- spaghetti
(23, 1002, 2, 2), -- Beans Canned
(24, 1002, 3, 1), -- Beans Dry
(25, 1002, 9, 2), -- Green Beans
(26, 1002, 7, 2), -- Corn
(27, 1002, 12, 1), -- Mixed Veg
(28, 1002, 20, 2), -- Soup
(29, 1002, 8, 1), -- Fruit
(30, 1002, 16, 2), -- Pasta
(31, 1002, 11, 4), -- Mac and Cheese
(32, 1002, 18, 4), -- Ramen
(33, 1002, 19, 1), -- Snacks
(34, 1002, 5, 1), -- Cereal
(35, 1002, 15, 1), -- Pancake
(36, 1002, 4, 1), -- Canned Meals
(37, 1002, 14, 1), -- Oil
(38, 1002, 17, 1), -- Peanut Butter
(39, 1002, 10, 1), -- Jelly

-- 4+ family size shopping event

(40, 1003, 6, 1), -- chicken
(41, 1003, 25, 3), -- tuna
(42, 1003, 23, 2), -- tomato sauce canned
(43, 1003, 21, 1), -- spaghetti
(44, 1003, 2, 3), -- Beans Canned
(45, 1003, 3, 2), -- Beans Dry
(46, 1003, 9, 3), -- Green Beans
(47, 1003, 7, 3), -- Corn
(48, 1003, 12, 2), -- Mixed Veg
(49, 1003, 20, 3), -- Soup
(50, 1003, 8, 2), -- Fruit
(51, 1003, 16, 3), -- Pasta
(52, 1003, 11, 4), -- Mac and Cheese
(53, 1003, 18, 4), -- Ramen
(54, 1003, 19, 2), -- Snacks
(55, 1003, 5, 2), -- Cereal
(56, 1003, 15, 2), -- Pancake
(57, 1003, 4, 1), -- Canned Meals

-- 4+ IH family size shopping event

(58, 1004, 6, 1), -- chicken
(59, 1004, 25, 3), -- tuna
(60, 1004, 23, 2), -- tomato sauce canned
(61, 1004, 21, 1), -- spaghetti
(62, 1004, 2, 3), -- Beans Canned
(63, 1004, 3, 2), -- Beans Dry
(64, 1004, 9, 3), -- Green Beans
(65, 1004, 7, 3), -- Corn
(66, 1004, 12, 2), -- Mixed Veg
(67, 1004, 20, 3), -- Soup
(68, 1004, 8, 2), -- Fruit
(69, 1004, 16, 3), -- Pasta
(70, 1004, 11, 4), -- Mac and Cheese
(71, 1004, 18, 4), -- Ramen
(72, 1004, 19, 2), -- Snacks
(73, 1004, 5, 2), -- Cereal
(74, 1004, 15, 2), -- Pancake
(75, 1004, 4, 1), -- Canned Meals
(76, 1004, 14, 1), -- Oil
(77, 1004, 17, 1), -- Peanut Butter
(78, 1004, 10, 1), -- Jelly

-- 8+ IH family size shopping event

(79, 1005, 6, 2), -- chicken
(80, 1005, 25, 4), -- tuna
(81, 1005, 23, 2), -- tomato sauce canned
(82, 1005, 21, 1), -- spaghetti
(83, 1005, 2, 3), -- Beans Canned
(84, 1005, 3, 2), -- Beans Dry
(85, 1005, 9, 3), -- Green Beans
(86, 1005, 7, 3), -- Corn
(87, 1005, 12, 2), -- Mixed Veg
(88, 1005, 20, 3), -- Soup
(89, 1005, 8, 3), -- Fruit
(90, 1005, 16, 3), -- Pasta
(91, 1005, 11, 5), -- Mac and Cheese
(92, 1005, 18, 5), -- Ramen
(93, 1005, 19, 2), -- Snacks
(94, 1005, 5, 2), -- Cereal
(95, 1005, 15, 2), -- Pancake
(96, 1005, 4, 2), -- Canned Meals
(97, 1005, 14, 1), -- Oil
(98, 1005, 17, 1), -- Peanut Butter
(99, 1005, 10, 1); -- Jelly



--
-- Indexes for dumped tables
--

--
-- Indexes for table `dbshoppingcounts`
--
ALTER TABLE `dbshoppingcounts`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dbshoppingcounts`
--
ALTER TABLE `dbshoppingcounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=314;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

