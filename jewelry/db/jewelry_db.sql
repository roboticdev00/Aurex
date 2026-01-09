-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 01, 2026 at 04:20 PM
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
-- Database: `jewelry_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `analytics_report`
--

CREATE TABLE `analytics_report` (
  `Report_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Total_Sales` int(11) NOT NULL,
  `Total_Revenues` int(11) NOT NULL,
  `Avg_Ratings` int(11) NOT NULL,
  `Review_Count` int(11) NOT NULL,
  `Rank` varchar(255) NOT NULL,
  `Report_Date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `Cart_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`Cart_ID`, `User_ID`) VALUES
(5, 6),
(4, 8),
(3, 65),
(1, 66),
(7, 67),
(6, 68);

-- --------------------------------------------------------

--
-- Table structure for table `cart_item`
--

CREATE TABLE `cart_item` (
  `Cart_Item_ID` int(11) NOT NULL,
  `Cart_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Quantity` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart_item`
--

INSERT INTO `cart_item` (`Cart_Item_ID`, `Cart_ID`, `Product_ID`, `Quantity`) VALUES
(5, 1, 2, '1'),
(7, 3, 11, '2');

-- --------------------------------------------------------

--
-- Table structure for table `category`
--

CREATE TABLE `category` (
  `Category_ID` int(11) NOT NULL,
  `Category_Name` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `category`
--

INSERT INTO `category` (`Category_ID`, `Category_Name`) VALUES
(1, 'Earrings'),
(2, 'Bracelet'),
(3, 'Necklace'),
(4, 'Rings');

-- --------------------------------------------------------

--
-- Table structure for table `loyalty`
--

CREATE TABLE `loyalty` (
  `Loyalty_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Current_Points` int(11) NOT NULL,
  `Total_Points` int(11) NOT NULL,
  `Tier_Level` varchar(255) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `Last_Activity_Date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notification`
--

CREATE TABLE `notification` (
  `Notification_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Message` varchar(255) NOT NULL,
  `Type` varchar(255) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `Timestamp` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `Order_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Order_Date` datetime DEFAULT current_timestamp(),
  `Total_Amount` decimal(10,2) NOT NULL,
  `Status` varchar(50) DEFAULT 'Pending',
  `Shipping_Address` text DEFAULT NULL,
  `Phone_Number` varchar(20) DEFAULT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `Latitude` decimal(10,8) DEFAULT NULL,
  `Longitude` decimal(11,8) DEFAULT NULL,
  `Tracking_Status` varchar(100) DEFAULT 'Order Placed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`Order_ID`, `User_ID`, `Order_Date`, `Total_Amount`, `Status`, `Shipping_Address`, `Phone_Number`, `Email`, `Latitude`, `Longitude`, `Tracking_Status`) VALUES
(1, 66, '2025-11-30 18:14:13', 1300.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(2, 66, '2025-11-30 18:23:27', 1040.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(3, 66, '2025-11-30 18:34:00', 420.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(4, 66, '2025-11-30 18:35:52', 1900.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(5, 66, '2025-11-30 19:07:09', 200.00, 'Cancelled', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(6, 65, '2025-12-13 14:57:03', 1000.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'agriam.rebejoe2@gmail.com', NULL, NULL, 'Order Placed'),
(7, 65, '2025-12-13 15:04:46', 400.00, 'Cancelled', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'agriam.rebejoe2@gmail.com', NULL, NULL, 'Order Placed'),
(8, 65, '2025-12-13 15:05:05', 400.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'agriam.rebejoe2@gmail.com', NULL, NULL, 'Order Placed'),
(9, 65, '2025-12-13 15:29:06', 200.00, 'Shipped', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'agriam.rebejoe2@gmail.com', NULL, NULL, 'Order Placed'),
(12, 65, '2025-12-13 16:11:58', 100.00, 'Shipped', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'agriam.rebejoe2@gmail.com', NULL, NULL, 'Order Placed'),
(13, 66, '2025-12-13 17:01:44', 180.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(14, 66, '2025-12-13 17:02:39', 450.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(15, 66, '2025-12-13 17:06:08', 180.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(16, 66, '2025-12-13 20:19:41', 5040.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', NULL, NULL, 'Order Placed'),
(17, 66, '2025-12-13 21:07:54', 720.00, 'Pending', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', 14.63112114, 120.96905586, 'Order Placed'),
(18, 66, '2025-12-13 21:30:48', 90.00, 'Cancelled', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', 0.00000000, 0.00000000, 'Order Placed'),
(19, 66, '2025-12-13 21:43:53', 180.00, 'Shipped', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'rebejoeagriam@yahoo.com', 10.63137830, 122.96244866, 'Order Placed'),
(20, 8, '2025-12-13 21:59:52', 550.00, 'Delivered', 'Dona Carmen Avenue, Bacolod, Negros Occidental 6100', '09662741018', 'juan@gmail.com', 10.63137678, 122.96244173, 'Order Placed'),
(21, 68, '2025-12-14 05:51:19', 250.00, 'Pending', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '4334', 'matt@gmail.com', 10.68426700, 122.97949791, 'Order Placed'),
(22, 68, '2025-12-14 16:53:16', 1650.00, 'Cancelled', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'matt@gmail.com', 10.68054853, 122.95476238, 'Order Placed'),
(23, 68, '2025-12-15 06:35:17', 17550.00, 'Delivered', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'matt@gmail.com', 10.67607125, 122.95392613, 'Order Placed'),
(24, 65, '2026-01-01 05:54:27', 9600.00, 'Pending', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'agriam.rebejoe2@gmail.com', 10.67997077, 122.94433395, 'Order Placed'),
(25, 6, '2026-01-01 12:33:19', 340.00, 'Delivered', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'renz@gmail.com', 10.67654217, 122.95480530, 'Order Placed'),
(26, 68, '2026-01-01 12:56:07', 2100.00, 'Delivered', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'matt@gmail.com', 10.67487636, 122.94909353, 'Order Placed'),
(27, 68, '2026-01-01 12:56:24', 540.00, 'Delivered', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'matt@gmail.com', 10.67356900, 122.95291300, 'Order Placed'),
(28, 68, '2026-01-01 12:56:41', 405.00, 'Delivered', '123 Sampaguita Street, Barangay Malinis, Quezon City, QUEZON, Laguna 6100', '09917586177', 'matt@gmail.com', 10.67725910, 122.95107166, 'Order Placed');

-- --------------------------------------------------------

--
-- Table structure for table `order_details`
--

CREATE TABLE `order_details` (
  `Order_Detail_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Price` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_item`
--

CREATE TABLE `order_item` (
  `Order_Item_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_item`
--

INSERT INTO `order_item` (`Order_Item_ID`, `Order_ID`, `Product_ID`, `Quantity`, `Price`) VALUES
(1, 1, 4, 3, 200.00),
(2, 1, 3, 7, 100.00),
(4, 2, 4, 1, 200.00),
(6, 4, 4, 3, 200.00),
(7, 4, 3, 3, 100.00),
(8, 4, 2, 5, 200.00),
(9, 5, 4, 1, 200.00),
(10, 6, 3, 4, 100.00),
(11, 6, 4, 3, 200.00),
(12, 7, 2, 2, 200.00),
(13, 8, 1, 2, 200.00),
(14, 9, 4, 1, 200.00),
(15, 12, 3, 1, 100.00),
(16, 13, 4, 1, 200.00),
(17, 14, 3, 5, 100.00),
(18, 15, 2, 1, 200.00),
(19, 16, 4, 28, 200.00),
(20, 17, 2, 4, 200.00),
(21, 18, 3, 1, 100.00),
(22, 19, 1, 1, 200.00),
(23, 20, 1, 2, 200.00),
(24, 21, 3, 1, 100.00),
(25, 22, 3, 1, 1500.00),
(26, 23, 3, 4, 1500.00),
(27, 23, 4, 3, 500.00),
(28, 23, 2, 1, 200.00),
(29, 23, 1, 1, 200.00),
(30, 23, 12, 50, 190.00),
(31, 24, 3, 5, 1500.00),
(32, 24, 2, 1, 200.00),
(33, 24, 1, 5, 200.00),
(34, 24, 11, 3, 600.00),
(35, 25, 12, 1, 190.00),
(36, 26, 12, 2, 190.00),
(37, 26, 11, 2, 600.00),
(38, 26, 10, 1, 520.00),
(39, 27, 11, 1, 600.00),
(40, 28, 7, 1, 450.00);

-- --------------------------------------------------------

--
-- Table structure for table `order_ratings`
--

CREATE TABLE `order_ratings` (
  `Rating_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Rating` int(11) NOT NULL COMMENT '1-5 stars',
  `Review_Text` text DEFAULT NULL,
  `Review_Image` varchar(255) DEFAULT NULL,
  `Rating_Date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_ratings`
--

INSERT INTO `order_ratings` (`Rating_ID`, `Order_ID`, `User_ID`, `Product_ID`, `Rating`, `Review_Text`, `Review_Image`, `Rating_Date`) VALUES
(1, 27, 68, 11, 3, 'HAPPY NEW YEAR BAI', 'images/reviews/review_27_68_1767276435.jpg', '2026-01-01 22:07:28'),
(2, 28, 68, 7, 5, '', NULL, '2026-01-01 22:49:45');

-- --------------------------------------------------------

--
-- Table structure for table `payment`
--

CREATE TABLE `payment` (
  `Payment_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Payment_Method` varchar(50) NOT NULL,
  `Cardholder_Name` varchar(100) DEFAULT NULL,
  `Card_Number` varchar(20) DEFAULT NULL,
  `Expiry_Date` varchar(10) DEFAULT NULL,
  `CVV` varchar(10) DEFAULT NULL,
  `Payment_Status` varchar(50) DEFAULT 'Pending',
  `Created_At` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product`
--

CREATE TABLE `product` (
  `Product_ID` int(11) NOT NULL,
  `Category_ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Price` int(11) NOT NULL,
  `Stock` varchar(255) NOT NULL,
  `Availability` varchar(255) NOT NULL,
  `Images` varchar(255) NOT NULL,
  `Rating` varchar(255) NOT NULL,
  `Avg_Rating` decimal(3,2) DEFAULT 0.00,
  `Rating_Count` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product`
--

INSERT INTO `product` (`Product_ID`, `Category_ID`, `Name`, `Description`, `Price`, `Stock`, `Availability`, `Images`, `Rating`, `Avg_Rating`, `Rating_Count`) VALUES
(1, 2, 'Aurex Lumina Tennis Bracelet', 's', 200, '101', 'In Stock', 'images/products/prod_693e53761b6c45.44379145.jpg', '0', 0.00, 0),
(2, 3, 'Aurex Solitaire Oval Pendant', 'avail', 200, '15', 'In Stock', 'images/products/prod_693e5356639b54.32532241.jpg', '0', 0.00, 0),
(3, 4, 'Aurex Zenith Twist Ring', 'Avail', 1500, '0', 'Out of Stock', 'images/products/prod_693e52d1d1df09.89042734.jpg', '0', 0.00, 0),
(4, 1, 'Aurex Zenith Huggie Hoops', 'avail', 500, '7', 'In Stock', 'images/products/prod_693e52796e42c0.18972934.jpg', '0', 0.00, 0),
(7, 3, 'Aurex Linea Necklace', 'A delicate, 18k gold-plated line necklace.', 450, '50', 'In Stock', 'images/products/prod_693f9192a47467.11369259.jpg', '0', 5.00, 1),
(8, 4, 'Aurex Vertex Ring', 'Stunning sterling silver ring with a peak CZ stone.', 350, '30', 'In Stock', 'images/products/prod_693f9167b14d25.23176528.jpg', '0', 0.00, 0),
(9, 1, 'Aurex Droplet Earrings', 'Tear-drop shaped gold earrings, perfect for everyday wear.', 280, '75', 'In Stock', 'images/products/prod_693f8f9be85c52.00952147.jpg', '0', 0.00, 0),
(10, 2, 'Aurex Meridian Bangle', 'Chunky, polished stainless steel bangle bracelet.', 520, '20', 'In Stock', 'images/products/prod_693f907edd8749.04287538.jpg', '0', 0.00, 0),
(11, 4, 'Aurex Solitaire Band', 'Classic silver solitaire wedding band.', 600, '40', 'In Stock', 'images/products/prod_693f934d5bf4c3.99209419.jpg', '0', 3.00, 1),
(12, 1, 'Aurex Halo Studs', 'Small, circular stud earrings with pavé setting.', 190, '40', 'In Stock', 'images/products/prod_693f92feb7c259.54917090.jpg', '0', 0.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `review`
--

CREATE TABLE `review` (
  `Review_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Rating` varchar(255) NOT NULL,
  `Comment` varchar(255) NOT NULL,
  `Media` varchar(255) NOT NULL,
  `Helpful_Votes` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shipment`
--

CREATE TABLE `shipment` (
  `Shipment_ID` int(11) NOT NULL,
  `Order_ID` int(11) NOT NULL,
  `Tracking_Num` int(11) NOT NULL,
  `Courier` varchar(255) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `Estimated_Delivery_Date` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subscription`
--

CREATE TABLE `subscription` (
  `Subscription_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Type` varchar(255) NOT NULL,
  `Channel` varchar(255) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `Created_At` datetime NOT NULL,
  `Updated_At` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_order`
--

CREATE TABLE `tbl_order` (
  `Order_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Product_ID` int(11) NOT NULL,
  `Quantity` int(11) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `Total_Amount` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket`
--

CREATE TABLE `ticket` (
  `Ticket_ID` int(11) NOT NULL,
  `User_ID` int(11) NOT NULL,
  `Subject` varchar(255) NOT NULL,
  `Description` varchar(255) NOT NULL,
  `Status` varchar(255) NOT NULL,
  `CreatedDate` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `User_ID` int(11) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Email` varchar(255) NOT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `Phone` int(11) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `Profile_Picture` varchar(255) DEFAULT NULL,
  `Role` varchar(255) NOT NULL,
  `Is_Logged_In` tinyint(1) NOT NULL DEFAULT 0,
  `user_secret_key` varchar(255) DEFAULT NULL,
  `last_2fa_verification` datetime DEFAULT NULL,
  `google_id` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`User_ID`, `Name`, `Email`, `Password`, `Phone`, `Address`, `Profile_Picture`, `Role`, `Is_Logged_In`, `user_secret_key`, `last_2fa_verification`, `google_id`) VALUES
(6, 'renz', 'renz@gmail.com', '$2y$10$paZQy2EN7lWsz9OllJk/U.uAlifusQjDFMt0GFqgA.KTDa7kPWCCG', NULL, NULL, NULL, 'customer', 0, 'RMPOQ4AYFPIZK2XN', '2026-01-01 19:32:38', NULL),
(7, 'france', 'france@gmail.com', '$2y$10$q03msVIs3x0dUhwhaQ9jvOkAj1/fyJ3GN7G4LOzUX91gtSlrBFE5S', NULL, NULL, NULL, 'customer', 0, 'QZ7WWFYSQ5DJPGM4', '2025-10-21 18:30:24', NULL),
(8, 'juan', 'juan@gmail.com', '$2y$10$ZKRZeLLijaJwHHCTNG7GHeEwON.aTNDuhIf6X5Gf/5iwvQW5S/OLy', NULL, NULL, NULL, 'customer', 0, 'DNRXPIRQ53WZXZ5S', '2025-12-14 03:25:41', NULL),
(65, 'rebejoe', 'agriam.rebejoe2@gmail.com', '$2y$10$joxDqs08hceENEegNXkfZOlpDByl1ICPQP2q9nI7jpfdQyKKTCDg6', NULL, NULL, NULL, 'customer', 0, 'TTCFK37EJPDJHTZ2', '2025-12-13 21:55:51', NULL),
(66, 'Benjo', 'rebejoeagriam@yahoo.com', '$2y$10$jylCgaUlLhZ6f3I1jQVi.u9gl0XI.pOovujQonZwxJl1VhIVpGAhe', NULL, NULL, NULL, 'customer', 0, 'XBOFLIT3PEOAXGDO', '2025-12-13 23:49:12', NULL),
(67, 'Admin', 'admin@jewelry.com', '$2y$10$skWmHrcQa/KVV/Jq54teVulHPCWFriBJQch0JGSRMbbloUxPmFEX6', NULL, '123 Admin Street', NULL, 'admin', 0, NULL, NULL, NULL),
(68, 'matt', 'matt@gmail.com', '$2y$10$CNgy.EYcp90vVgzJL/khD.DmMcBH1K0eChPxPp6t2/ZYRUntPDgTq', NULL, NULL, NULL, 'customer', 0, 'ESHG53QCP36SOULB', '2026-01-01 13:14:24', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_report`
--
ALTER TABLE `analytics_report`
  ADD PRIMARY KEY (`Report_ID`),
  ADD KEY `Product_ID` (`Product_ID`);

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`Cart_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `cart_item`
--
ALTER TABLE `cart_item`
  ADD PRIMARY KEY (`Cart_Item_ID`),
  ADD KEY `Cart_ID` (`Cart_ID`,`Product_ID`),
  ADD KEY `Product_ID` (`Product_ID`);

--
-- Indexes for table `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`Category_ID`);

--
-- Indexes for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD PRIMARY KEY (`Loyalty_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `notification`
--
ALTER TABLE `notification`
  ADD PRIMARY KEY (`Notification_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `order_details`
--
ALTER TABLE `order_details`
  ADD PRIMARY KEY (`Order_Detail_ID`),
  ADD KEY `Product_ID` (`Product_ID`),
  ADD KEY `Order_ID` (`Order_ID`);

--
-- Indexes for table `order_item`
--
ALTER TABLE `order_item`
  ADD PRIMARY KEY (`Order_Item_ID`),
  ADD KEY `Order_ID` (`Order_ID`),
  ADD KEY `Product_ID` (`Product_ID`);

--
-- Indexes for table `order_ratings`
--
ALTER TABLE `order_ratings`
  ADD PRIMARY KEY (`Rating_ID`),
  ADD KEY `Order_ID` (`Order_ID`),
  ADD KEY `User_ID` (`User_ID`),
  ADD KEY `Product_ID` (`Product_ID`);

--
-- Indexes for table `payment`
--
ALTER TABLE `payment`
  ADD PRIMARY KEY (`Payment_ID`),
  ADD KEY `payment_orders_fk` (`Order_ID`);

--
-- Indexes for table `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`Product_ID`),
  ADD KEY `Category_ID` (`Category_ID`);

--
-- Indexes for table `review`
--
ALTER TABLE `review`
  ADD PRIMARY KEY (`Review_ID`),
  ADD KEY `User_ID` (`User_ID`,`Product_ID`),
  ADD KEY `Product_ID` (`Product_ID`);

--
-- Indexes for table `shipment`
--
ALTER TABLE `shipment`
  ADD PRIMARY KEY (`Shipment_ID`),
  ADD KEY `Order_ID` (`Order_ID`);

--
-- Indexes for table `subscription`
--
ALTER TABLE `subscription`
  ADD PRIMARY KEY (`Subscription_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `tbl_order`
--
ALTER TABLE `tbl_order`
  ADD PRIMARY KEY (`Order_ID`),
  ADD KEY `User_ID` (`User_ID`,`Product_ID`),
  ADD KEY `Product_ID` (`Product_ID`);

--
-- Indexes for table `ticket`
--
ALTER TABLE `ticket`
  ADD PRIMARY KEY (`Ticket_ID`),
  ADD KEY `User_ID` (`User_ID`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`User_ID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `Order_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `order_item`
--
ALTER TABLE `order_item`
  MODIFY `Order_Item_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `order_ratings`
--
ALTER TABLE `order_ratings`
  MODIFY `Rating_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `payment`
--
ALTER TABLE `payment`
  MODIFY `Payment_ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product`
--
ALTER TABLE `product`
  MODIFY `Product_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `User_ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=69;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `analytics_report`
--
ALTER TABLE `analytics_report`
  ADD CONSTRAINT `analytics_report_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `cart_item`
--
ALTER TABLE `cart_item`
  ADD CONSTRAINT `cart_item_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `cart_item_ibfk_2` FOREIGN KEY (`Cart_ID`) REFERENCES `cart` (`Cart_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `loyalty`
--
ALTER TABLE `loyalty`
  ADD CONSTRAINT `loyalty_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notification`
--
ALTER TABLE `notification`
  ADD CONSTRAINT `notification_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`);

--
-- Constraints for table `order_details`
--
ALTER TABLE `order_details`
  ADD CONSTRAINT `order_details_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_details_ibfk_2` FOREIGN KEY (`Order_ID`) REFERENCES `tbl_order` (`Order_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `order_item`
--
ALTER TABLE `order_item`
  ADD CONSTRAINT `order_item_ibfk_1` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`),
  ADD CONSTRAINT `order_item_ibfk_2` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`);

--
-- Constraints for table `order_ratings`
--
ALTER TABLE `order_ratings`
  ADD CONSTRAINT `order_ratings_ibfk_1` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_ratings_ibfk_2` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_ratings_ibfk_3` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE;

--
-- Constraints for table `payment`
--
ALTER TABLE `payment`
  ADD CONSTRAINT `payment_orders_fk` FOREIGN KEY (`Order_ID`) REFERENCES `orders` (`Order_ID`) ON DELETE CASCADE;

--
-- Constraints for table `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `product_ibfk_1` FOREIGN KEY (`Category_ID`) REFERENCES `category` (`Category_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `review`
--
ALTER TABLE `review`
  ADD CONSTRAINT `review_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `review_ibfk_2` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `shipment`
--
ALTER TABLE `shipment`
  ADD CONSTRAINT `shipment_ibfk_1` FOREIGN KEY (`Order_ID`) REFERENCES `tbl_order` (`Order_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `subscription`
--
ALTER TABLE `subscription`
  ADD CONSTRAINT `subscription_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_order`
--
ALTER TABLE `tbl_order`
  ADD CONSTRAINT `tbl_order_ibfk_1` FOREIGN KEY (`Product_ID`) REFERENCES `product` (`Product_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket`
--
ALTER TABLE `ticket`
  ADD CONSTRAINT `ticket_ibfk_1` FOREIGN KEY (`User_ID`) REFERENCES `user` (`User_ID`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
