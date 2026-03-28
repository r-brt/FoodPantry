-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 27, 2026 at 05:01 PM
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
-- Table structure for table `dbshoppingevent`
--

DROP TABLE IF EXISTS `dbshoppingevent`;
CREATE TABLE `dbshoppingevent` (
  `id` int(11) NOT NULL,
  `personId` varchar(11) NOT NULL,
  `familySize` varchar(11) NOT NULL,
  `date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dbshoppingevent`
--

INSERT INTO `dbshoppingevent` (`id`, `personId`, `familySize`, `date`) VALUES
(1001, '3', '1-3', '2026-01-20'),
(1002, '3', '1-3-IH', '2026-01-20'),
(1003, '2', '4+', '2026-02-04'),
(1004, '2', '4+-IH', '2026-02-04'),
(1005, '2', '8+-IH', '2026-02-04');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `dbshoppingevent`
--
ALTER TABLE `dbshoppingevent`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `dbshoppingevent`
--
ALTER TABLE `dbshoppingevent`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1005;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
