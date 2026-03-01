-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 01, 2026 at 02:10 PM
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
-- Database: `smart_energy`
--

-- --------------------------------------------------------

--
-- Table structure for table `energy_consumption`
--

CREATE TABLE `energy_consumption` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `appliance_id` int(11) DEFAULT NULL,
  `appliance_name` varchar(100) NOT NULL,
  `hours_used` decimal(5,2) NOT NULL DEFAULT 0.00,
  `wattage` int(11) NOT NULL DEFAULT 0,
  `consumption_kwh` decimal(10,4) NOT NULL,
  `date_recorded` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `energy_consumption`
--

INSERT INTO `energy_consumption` (`id`, `user_id`, `appliance_id`, `appliance_name`, `hours_used`, `wattage`, `consumption_kwh`, `date_recorded`) VALUES
(3, 1, 337, 'Air Conditioner (1 Ton Inverter)', 12.00, 1000, 12.0000, '2026-03-01'),
(4, 1, 338, 'Air Conditioner (1.5 Ton Inverter)', 40.00, 1500, 60.0000, '2026-03-01'),
(7, 1, 341, 'Water Heater (Geyser 15L)', 48.00, 2000, 96.0000, '2026-03-01'),
(10, 1, 338, 'Air Conditioner (1.5 Ton Inverter)', 10.00, 1500, 15.0000, '2026-03-01');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `energy_consumption`
--
ALTER TABLE `energy_consumption`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `energy_consumption`
--
ALTER TABLE `energy_consumption`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `energy_consumption`
--
ALTER TABLE `energy_consumption`
  ADD CONSTRAINT `energy_consumption_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
