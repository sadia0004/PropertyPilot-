-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 22, 2025 at 04:34 PM
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
-- Database: `property`
--

-- --------------------------------------------------------

--
-- Table structure for table `addtenants`
--

CREATE TABLE `addtenants` (
  `tenant_id` int(11) NOT NULL,
  `landlord_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `apartment_no` varchar(50) NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `family_members` int(11) DEFAULT NULL,
  `additional_info` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `addtenants`
--

INSERT INTO `addtenants` (`tenant_id`, `landlord_id`, `name`, `apartment_no`, `monthly_rent`, `family_members`, `additional_info`) VALUES
(27, 15, 'Tadique Ahmed', 'F11', 15000.00, 7, 'p'),
(37, 15, 'Akhi Akter', 'F22', 14000.00, 5, 'shit'),
(46, 15, 'Kawsar Mia', 'F-33', 13000.00, 8, '54'),
(48, 15, 'Tahmid', 'F-3000', 18000.00, 4, 'newly added'),
(64, 51, 'Shakil Vuiyan', 'G1', 10000.00, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `issue_category` enum('Plumbing','Electrical','Appliances','HVAC','Pest Control','General') NOT NULL,
  `issue_description` text NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `permission_to_enter` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance_requests`
--

INSERT INTO `maintenance_requests` (`request_id`, `tenant_id`, `landlord_id`, `property_id`, `issue_category`, `issue_description`, `photo`, `permission_to_enter`, `status`, `created_at`) VALUES
(1, 48, 15, 12, 'Electrical', 'The switch board is not working.', 'uploads/1755185496_noise image.webp', 1, 'Pending', '2025-08-14 15:31:36');

-- --------------------------------------------------------

--
-- Table structure for table `meeting_schedule`
--

CREATE TABLE `meeting_schedule` (
  `scheduleID` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `meetingType` varchar(100) DEFAULT NULL,
  `EventDescription` text DEFAULT NULL,
  `date` date NOT NULL,
  `time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meeting_schedule`
--

INSERT INTO `meeting_schedule` (`scheduleID`, `landlord_id`, `tenant_id`, `name`, `meetingType`, `EventDescription`, `date`, `time`) VALUES
(6, 15, 27, 'Tadique Ahmed', 'Online', 'Meeting Link: https://propertymeet.io/y73gaek8bx\n\nNeed to discuss about the upcoming community program', '2025-08-14', '15:00:00'),
(7, 15, 37, 'Akhi Akter', 'Online', 'Meeting Link: https://propertymeet.io/y73gaek8bx\n\nNeed to discuss about the upcoming community program', '2025-08-14', '15:00:00'),
(8, 15, 46, 'Kawsar Mia', 'Online', 'Meeting Link: https://propertymeet.io/y73gaek8bx\n\nNeed to discuss about the upcoming community program', '2025-08-14', '15:00:00'),
(9, 15, 46, 'Kawsar Mia', 'In-Person', 'Need to talk about Eid-ul-Adha and korbani.', '2025-06-01', '15:01:00'),
(10, 15, 48, 'Tahmid', 'In-Person', 'About rent increase discussion.', '2025-08-28', '15:25:00'),
(11, 51, 64, 'Shakil Vuiyan', 'In-Person', 'Meeting for some agreements.', '2025-08-26', '10:00:00'),
(12, 15, 27, 'Tadique Ahmed', 'Online', 'Meeting Link: https://propertymeet.io/w3dpbaweey\n\nDiscussion about the maintanance issue.', '2025-09-06', '18:30:00');

-- --------------------------------------------------------

--
-- Table structure for table `post_images`
--

CREATE TABLE `post_images` (
  `image_id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `post_images`
--

INSERT INTO `post_images` (`image_id`, `post_id`, `image_path`) VALUES
(4, 4, 'uploads/properties/1755849798_68a8244623095_A-2.webp'),
(5, 4, 'uploads/properties/1755849798_68a82446235da_A-2 (2).webp'),
(6, 4, 'uploads/properties/1755849798_68a8244623b73_A-2.jpeg'),
(7, 5, 'uploads/properties/1755853341_68a8321d65a9d_OIP (2).webp'),
(8, 5, 'uploads/properties/1755853341_68a8321d66a34_OIP (1).webp'),
(9, 5, 'uploads/properties/1755853341_68a8321d66d67_OIP.webp'),
(13, 7, 'uploads/properties/1755853520_68a832d016f59_bed2.webp'),
(14, 7, 'uploads/properties/1755853520_68a832d017390_bed 1.webp'),
(15, 8, 'uploads/properties/1755854204_68a8357c249e7_new2.webp'),
(16, 8, 'uploads/properties/1755854204_68a8357c251f7_new1.webp'),
(19, 11, 'uploads/properties/1755860121_68a84c9976baa_new rr.webp'),
(20, 11, 'uploads/properties/1755860121_68a84c997ff83_ne rr.webp'),
(21, 11, 'uploads/properties/1755860121_68a84c99809e3_new.jpg');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `apartment_no` varchar(50) NOT NULL,
  `apartment_rent` decimal(10,2) NOT NULL,
  `apartment_status` enum('Vacant','Occupied') NOT NULL,
  `floor_no` int(50) DEFAULT NULL,
  `apartment_type` varchar(70) DEFAULT NULL,
  `apartment_size` int(60) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`property_id`, `landlord_id`, `apartment_no`, `apartment_rent`, `apartment_status`, `floor_no`, `apartment_type`, `apartment_size`) VALUES
(1, 15, 'F11', 15000.00, 'Occupied', 1, '1BHK', 1200),
(2, 15, 'F22', 14000.00, 'Occupied', 2, '1BHK', 1300),
(5, 28, 'A1', 17000.00, 'Occupied', 2, '3BHK', 1500),
(6, 28, 'A-0', 30000.00, 'Occupied', 4, '4BHK', 1500),
(8, 15, 'F-33', 13000.00, 'Occupied', 3, '2BHK', 1200),
(9, 15, 'F-30', 14000.00, 'Vacant', 3, '2BHK', 1200),
(11, 15, 'F-300', 14000.00, 'Occupied', 3, '2BHK', 1200),
(12, 15, 'F-3000', 18000.00, 'Occupied', 3, '2BHK', 1800),
(13, 51, 'G1', 10000.00, 'Occupied', 1, '1.5 BHK', 1000),
(14, 51, 'G2', 20000.00, 'Vacant', 2, '3BHK', 1500),
(15, 51, 'G3', 15000.00, 'Vacant', 3, '2.5BHK', 1300),
(16, 28, 'A-2', 9500.00, 'Vacant', 2, '1.8BHK', 1000),
(17, 28, 'A-3', 16000.00, 'Vacant', 3, '3.5BHK', 1400);

-- --------------------------------------------------------

--
-- Table structure for table `rentandbill`
--

CREATE TABLE `rentandbill` (
  `rent_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `apartment_no` varchar(50) NOT NULL,
  `rent_amount` decimal(10,2) NOT NULL,
  `previous_due` decimal(10,2) DEFAULT 0.00,
  `water_bill` decimal(10,2) DEFAULT 0.00,
  `utility_bill` decimal(10,2) DEFAULT 0.00,
  `guard_bill` decimal(10,2) DEFAULT 0.00,
  `billing_date` date NOT NULL,
  `satus` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rentandbill`
--

INSERT INTO `rentandbill` (`rent_id`, `landlord_id`, `tenant_id`, `apartment_no`, `rent_amount`, `previous_due`, `water_bill`, `utility_bill`, `guard_bill`, `billing_date`, `satus`) VALUES
(7, 15, 37, 'F22', 14000.00, 1000.00, 1800.00, 1000.00, 300.00, '2025-08-03', '2025-08-03 00:44:05'),
(8, 15, 46, 'F-33', 13000.00, 0.00, 1800.00, 1000.00, 400.00, '2025-08-14', NULL),
(15, 51, 64, 'G1', 10000.00, 0.00, 1200.00, 1000.00, 200.00, '2025-08-22', NULL),
(16, 15, 27, 'F11', 15000.00, 0.00, 1000.00, 1000.00, 300.00, '2025-09-01', NULL),
(17, 15, 27, 'F11', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-07-01', 'Paid'),
(18, 15, 27, 'F11', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-06-01', 'Paid'),
(19, 15, 27, 'F11', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-05-01', 'Paid'),
(20, 15, 27, 'F11', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-04-01', 'Paid'),
(21, 15, 27, 'F11', 0.00, 0.00, 0.00, 0.00, 0.00, '2025-03-01', 'Paid'),
(22, 15, 27, 'F11', 0.00, 3000.00, 0.00, 0.00, 0.00, '2025-08-01', 'Partially Paid');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `tenant_id` int(11) NOT NULL,
  `name` varchar(60) DEFAULT NULL,
  `profession` varchar(255) DEFAULT NULL,
  `apartment_no` varchar(50) NOT NULL,
  `rent_date` date DEFAULT NULL,
  `family_members` int(11) DEFAULT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`tenant_id`, `name`, `profession`, `apartment_no`, `rent_date`, `family_members`, `emergency_contact`) VALUES
(37, 'Akhi Akter', 'Arabic Teacher', 'F22', '2025-06-01', 5, '01879172009'),
(46, 'Kawsar Mia', 'Player', 'F-33', '2025-08-03', 8, '34534523'),
(48, 'Tahmid', 'Painter', 'F-3000', '2025-08-04', 4, '34534523'),
(59, 'Sabiha Begum', 'Company Job', 'A1', '2025-09-01', 5, '01879172889'),
(61, 'Hanif Rahman', 'teacher', 'A-0', '2025-08-01', 6, '241234242');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` varchar(255) NOT NULL,
  `rent_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('Card','Mobile Banking') NOT NULL,
  `status` enum('Pending','Paid','Partially Paid') NOT NULL DEFAULT 'Pending',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `rent_id`, `tenant_id`, `landlord_id`, `amount`, `due_amount`, `payment_method`, `status`, `transaction_date`) VALUES
('TXN-68a852c463c3b', 19, 27, 15, 17500.00, 0.00, 'Mobile Banking', 'Paid', '2025-05-02 11:21:55'),
('TXN-68a852dcaa95b', 18, 27, 15, 18700.00, 0.00, 'Card', 'Paid', '2025-06-01 11:22:48'),
('TXN-68a8530fdc1ef', 17, 27, 15, 17500.00, 0.00, 'Mobile Banking', 'Paid', '2025-07-06 11:23:12'),
('TXN-68a85c0f52763', 21, 27, 15, 17300.00, 0.00, 'Mobile Banking', 'Paid', '2025-03-02 12:01:34'),
('TXN-68a85c59a801e', 20, 27, 15, 17500.00, 0.00, 'Card', 'Paid', '2025-04-08 12:02:46'),
('TXN-68a869652ca70', 22, 27, 15, 15000.00, 3000.00, 'Mobile Banking', 'Partially Paid', '2025-08-22 12:58:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullName` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phoneNumber` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `profilePhoto` varchar(255) DEFAULT NULL,
  `nationalId` varchar(50) NOT NULL,
  `userRole` enum('landlord','tenant','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `fullName`, `email`, `phoneNumber`, `password`, `profilePhoto`, `nationalId`, `userRole`, `created_at`) VALUES
(15, 'Sadia Ahmed', 'sadia@gmail.com', '1222222', '$2y$10$99OgkbzLVVejq74Vx2lW/u0V54ngw49p8sIzGUfBSF8G2IhzDKChy', 'uploads/1754979356_FB_IMG_1647281671245.jpg', '122333312', 'landlord', '2025-06-26 18:15:06'),
(27, 'Tadique Ahmed', 'rayu@gmail.com', '0189288922', '$2y$10$Z0Twi0asm4iOsyNZFOtyLuKCQEnT4LKu5sDdAGfvkkb6Iy.jjJz.q', 'uploads/1753948676_man.webp', '0989299282', 'tenant', '2025-07-31 07:57:56'),
(28, 'Surma Ahmed', 'surma@gmail.com', '0999192', '$2y$10$aDr0pfX5CCUHUF.Kq5xFA.Nm6gfnmP8KVZ9roNxR80N9X6qykC8c2', '', '1232233', 'landlord', '2025-07-31 10:10:28'),
(37, 'Akhi Akter', 'akhi@gmail.com', '3463456345', '$2y$10$HAhbV1gQJJZmg3wExspGn.ObaiUwp.hAN.OLKhGEdoYQT3Slbe5gG', '', '3523523', 'tenant', '2025-07-31 21:12:09'),
(46, 'Kawsar Mia', 'kasu@gmail.com', '12213123123', '$2y$10$XAakTdg2Z5J61hJkwmn4Z.ujmOWeBRMUxpFMW7DJb1uIZNL.BYzUO', '', '4323412312', 'tenant', '2025-08-02 19:26:53'),
(48, 'Tahmid', 'ta@gmail.com', '31241241', '$2y$10$em6I4hKXltvvZ9.eYk83ruOMe0vdSmAYIqmo/7pOHA5JPYQDrIU8G', 'uploads/1755182060_man.webp', '324211', 'tenant', '2025-08-10 06:12:43'),
(50, 'Faizan khan', 'faizu@gmail.com', '342423423', '$2y$10$nF9fEp9leLY7adUDDA7vNu/ewZbECMvPGc8jA/0xVQTrE0w.J67gi', 'uploads/1755794047_noise image.webp', '24242421', 'admin', '2025-08-21 16:34:07'),
(51, 'Faisal Khan', 'faisal@gmail.com', '3131241', '$2y$10$orJKazNgTadeee5kL5yoGO8e9uQNGJ.lshtsaDmnRXx3jvOgT70re', '', '432342342', 'landlord', '2025-08-21 16:46:49'),
(59, 'Sabiha Begum', 'sabiha@gmail.com', '0189889922', '$2y$10$U8rjeIrVayvxoCddLipbAeGLw/W5aa3iqvRp592FS6UZCxyqdCGaS', '', '1138987662', 'tenant', '2025-08-21 20:29:01'),
(60, 'Sheikh Jamil', 'jamil@gmail.com', '412342423', '$2y$10$IAus2SiiSRiubZdy84liWO9/YzOtDc6qNVSDVbcqqM99StJByHtoK', '', '3424231', 'tenant', '2025-08-21 20:30:54'),
(61, 'Hanif Rahman', 'hanif@gmail.com', '0123123123`', '$2y$10$XIaZR7UKNKGARvwr94a0ee8KpfFKisfc70UVsF1mUJtQ7wAuapGqC', '', '1313`21212', 'tenant', '2025-08-21 20:36:33'),
(63, 'Rafique Ahmed', 'rafiq@gmail.com', '01222222', '$2y$10$7aoKyXqs8AzaxlqATmutCuwjQ5PkwcjA1Gg8HNEWohC8oHJ/NcL7i', '', '14142342', 'tenant', '2025-08-22 05:41:29'),
(64, 'Shakil Vuiyan', 'shakil@gmail.com', '23123423', '$2y$10$iBk6aA8VlvqH1EGoKFCOjem19xTSNUfg2jcXde0B.rITQjOYvHUSK', '', '3421342342', 'tenant', '2025-08-22 06:53:14');

-- --------------------------------------------------------

--
-- Table structure for table `vacancy_posts`
--

CREATE TABLE `vacancy_posts` (
  `post_id` int(11) NOT NULL,
  `landlord_id` int(11) NOT NULL,
  `property_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `location` varchar(80) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vacancy_posts`
--

INSERT INTO `vacancy_posts` (`post_id`, `landlord_id`, `property_id`, `title`, `location`, `description`, `created_at`) VALUES
(4, 28, 16, 'Stylish 2-Bedroom Apartment in the Heart of Gulshan', 'Gulshan', 'Looking for a convenient and affordable place to call your own? This cozy 1-bedroom apartment in the Mohammadpur area is the perfect fit. The unit is well-maintained and features a functional kitchen and a comfortable living space.', '2025-08-22 08:03:18'),
(5, 51, 14, '3-Bedroom size apartment in Mirpur.', 'Mirpur, Dhaka', 'Located with excellent access to public transportation, markets, and universities, this apartment offers incredible value. All essential utilities are reliably available. An excellent choice for students or a single professional seeking a practical and budget-friendly home.', '2025-08-22 09:02:21'),
(7, 51, 15, 'Stylish Apartment in the Heart of Mirpur beside Metro Station.', 'Mirpur, Dhaka', 'This cozy 1-bedroom apartment in the Mohammadpur area is the perfect fit. The unit is well-maintained and features a functional kitchen and a comfortable living space.', '2025-08-22 09:05:20'),
(8, 28, 17, 'Stylish Apartment in the peak area of Dhanmondi.', 'Dhnmondi', 'Your perfect family home awaits! This spacious 1500 sq ft apartment offers three large bedrooms, a separate dining area, and a balcony with a pleasant view. The building is located on a quiet, tree-lined street, ensuring a peaceful living environment.', '2025-08-22 09:16:44'),
(11, 15, 9, '2-Bedroom size apartment in Banani.', 'Banani', 'Looking for a convenient and affordable place to call your own? This cozy 2-bedroom apartment in the Mohammadpur area is the perfect fit. The unit is well-maintained and features a functional kitchen and a comfortable living space.', '2025-08-22 10:55:21');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addtenants`
--
ALTER TABLE `addtenants`
  ADD PRIMARY KEY (`tenant_id`),
  ADD KEY `fk_tenant_property` (`apartment_no`),
  ADD KEY `fk_tenant_property_landlord` (`landlord_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `meeting_schedule`
--
ALTER TABLE `meeting_schedule`
  ADD PRIMARY KEY (`scheduleID`),
  ADD KEY `fk_meeting_landlord` (`landlord_id`),
  ADD KEY `fk_meeting_tenant` (`tenant_id`);

--
-- Indexes for table `post_images`
--
ALTER TABLE `post_images`
  ADD PRIMARY KEY (`image_id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`property_id`),
  ADD UNIQUE KEY `apartment_no_unique` (`apartment_no`),
  ADD KEY `fk_landlord_property` (`landlord_id`);

--
-- Indexes for table `rentandbill`
--
ALTER TABLE `rentandbill`
  ADD PRIMARY KEY (`rent_id`),
  ADD KEY `fk_rab_landlord` (`landlord_id`),
  ADD KEY `fk_rab_tenant` (`tenant_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`tenant_id`),
  ADD KEY `fk2_tenant_property` (`apartment_no`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `rent_id` (`rent_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `landlord_id` (`landlord_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vacancy_posts`
--
ALTER TABLE `vacancy_posts`
  ADD PRIMARY KEY (`post_id`),
  ADD KEY `landlord_id` (`landlord_id`),
  ADD KEY `property_id` (`property_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `meeting_schedule`
--
ALTER TABLE `meeting_schedule`
  MODIFY `scheduleID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `post_images`
--
ALTER TABLE `post_images`
  MODIFY `image_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rentandbill`
--
ALTER TABLE `rentandbill`
  MODIFY `rent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `vacancy_posts`
--
ALTER TABLE `vacancy_posts`
  MODIFY `post_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addtenants`
--
ALTER TABLE `addtenants`
  ADD CONSTRAINT `fk1_tenant_user` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tenant_property` FOREIGN KEY (`apartment_no`) REFERENCES `properties` (`apartment_no`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tenant_property_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `properties` (`landlord_id`) ON DELETE CASCADE;

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maintenance_requests_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE;

--
-- Constraints for table `meeting_schedule`
--
ALTER TABLE `meeting_schedule`
  ADD CONSTRAINT `fk_meeting_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_meeting_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `post_images`
--
ALTER TABLE `post_images`
  ADD CONSTRAINT `fk_image_post` FOREIGN KEY (`post_id`) REFERENCES `vacancy_posts` (`post_id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_landlord_property` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rentandbill`
--
ALTER TABLE `rentandbill`
  ADD CONSTRAINT `fk_rab_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rab_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk2_tenant_property` FOREIGN KEY (`apartment_no`) REFERENCES `properties` (`apartment_no`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tenant_user` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_trans_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trans_rent` FOREIGN KEY (`rent_id`) REFERENCES `rentandbill` (`rent_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_trans_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vacancy_posts`
--
ALTER TABLE `vacancy_posts`
  ADD CONSTRAINT `fk_post_landlord` FOREIGN KEY (`landlord_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_post_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
