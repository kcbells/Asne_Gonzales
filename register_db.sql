-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 10:42 AM
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
-- Database: `register_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assigned_units`
--

CREATE TABLE `assigned_units` (
  `assigned_units_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `status` enum('available','occupied','pending downpayment') DEFAULT NULL,
  `downpayment` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance_requests`
--

CREATE TABLE `maintenance_requests` (
  `request_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','resolved') DEFAULT NULL,
  `assigned_staff` varchar(100) DEFAULT NULL,
  `date_reported` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `rent_id` int(11) DEFAULT NULL,
  `type` enum('downpayment','monthly') DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `datetime_paid` datetime DEFAULT NULL,
  `method` enum('cash','card') DEFAULT NULL,
  `status` enum('success','failed') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_schedule`
--

CREATE TABLE `payment_schedule` (
  `schedule_id` int(11) NOT NULL,
  `rent_id` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `amount_due` decimal(10,2) DEFAULT NULL,
  `status` enum('paid','unpaid') DEFAULT NULL,
  `payment_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `property_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `property_name` varchar(100) DEFAULT NULL,
  `type` enum('Condominium','Boarding House','Rental Home') DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `status` enum('available','unavailable','under maintenance','renovation') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`property_id`, `user_id`, `property_name`, `type`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 6, 'Darwin Homes', 'Condominium', 'Brgy 35 CDO', 'available', '2026-01-11 09:15:05', '2026-01-11 09:41:50');

--
-- Triggers `properties`
--
DELIMITER $$
CREATE TRIGGER `trg_properties_owner_role_ins` BEFORE INSERT ON `properties` FOR EACH ROW BEGIN
  IF (SELECT role FROM users WHERE user_id = NEW.user_id) <> 'Owner' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id must have role Owner';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_properties_owner_role_upd` BEFORE UPDATE ON `properties` FOR EACH ROW BEGIN
  IF (SELECT role FROM users WHERE user_id = NEW.user_id) <> 'Owner' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'user_id must have role Owner';
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `system_logs`
--

CREATE TABLE `system_logs` (
  `log_id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `datetime` datetime DEFAULT NULL,
  `ipaddress` varchar(50) DEFAULT NULL,
  `device` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tenant`
--

CREATE TABLE `tenant` (
  `tenant_id` int(11) NOT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant`
--

INSERT INTO `tenant` (`tenant_id`, `firstname`, `lastname`, `middlename`, `username`, `password`, `email`, `contact_no`) VALUES
(1, 'Ivan Louiz', 'Gonzales', 'Maglupay', 'Ivan_1234', '$2y$10$D5a9wHWnA/hRpowS4Tjuc.xYARVlFc7cbwZlUmqiCnPGy9dh5vQsK', 'ivan@gmail.com', '098798000'),
(9, 'K C Joy', 'Asne', 'Vicente', 'Kcjoy_1234', '$2y$10$PInMRI0Y07v7YUoynzWMSORHK7r.ZGwrxpIis0cRLf8dIsfATUmgG', 'kcjoy@gmail.com', '0999999999');

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `unit_id` int(11) NOT NULL,
  `property_id` int(11) DEFAULT NULL,
  `unit_number` varchar(50) DEFAULT NULL,
  `floor` varchar(20) DEFAULT NULL,
  `size` decimal(10,2) DEFAULT NULL,
  `monthly_rent` decimal(10,2) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` varchar(30) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `first_name`, `middle_name`, `last_name`, `role`, `email`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Admin_1234', 'K C Joy', 'Vicente', 'Asne', 'Admin', 'asne123@gmail.com', '$2y$10$hsunz7HrNdwRLS7YQKGeGuZ/thEDQmbQkgzN/1ySsOrssfFoT.S0G', 'active', '2026-01-11 08:29:13', '2026-01-11 09:11:35'),
(6, 'Darwin_1234', 'Darwin', 'Richards', 'Galudo', 'Owner', 'Darwin@gmailc.com', '$2y$10$saOvZhsPrYtkyafeGjdyZu9gUm1I8fTueJIemcQLZZtQopRJtVg7K', 'active', '2026-01-11 09:14:15', '2026-01-11 09:14:15');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assigned_units`
--
ALTER TABLE `assigned_units`
  ADD PRIMARY KEY (`assigned_units_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `unit_id` (`unit_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `rent_id` (`rent_id`);

--
-- Indexes for table `payment_schedule`
--
ALTER TABLE `payment_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `rent_id` (`rent_id`),
  ADD KEY `payment_id` (`payment_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`property_id`),
  ADD KEY `fk_properties_user` (`user_id`);

--
-- Indexes for table `system_logs`
--
ALTER TABLE `system_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `tenant`
--
ALTER TABLE `tenant`
  ADD PRIMARY KEY (`tenant_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`unit_id`),
  ADD KEY `property_id` (`property_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assigned_units`
--
ALTER TABLE `assigned_units`
  MODIFY `assigned_units_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `payment_schedule`
--
ALTER TABLE `payment_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `property_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `system_logs`
--
ALTER TABLE `system_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `tenant`
--
ALTER TABLE `tenant`
  MODIFY `tenant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assigned_units`
--
ALTER TABLE `assigned_units`
  ADD CONSTRAINT `assigned_units_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`),
  ADD CONSTRAINT `assigned_units_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`);

--
-- Constraints for table `maintenance_requests`
--
ALTER TABLE `maintenance_requests`
  ADD CONSTRAINT `maintenance_requests_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `units` (`unit_id`),
  ADD CONSTRAINT `maintenance_requests_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenant` (`tenant_id`);

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`rent_id`) REFERENCES `assigned_units` (`assigned_units_id`);

--
-- Constraints for table `payment_schedule`
--
ALTER TABLE `payment_schedule`
  ADD CONSTRAINT `payment_schedule_ibfk_1` FOREIGN KEY (`rent_id`) REFERENCES `assigned_units` (`assigned_units_id`),
  ADD CONSTRAINT `payment_schedule_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`);

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_properties_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON UPDATE CASCADE;

--
-- Constraints for table `units`
--
ALTER TABLE `units`
  ADD CONSTRAINT `units_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`property_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
