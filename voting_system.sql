-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 19, 2025 at 08:26 PM
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
-- Database: `voting_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Admin',
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'admin',
  `created_at` datetime NOT NULL,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password`, `name`, `role`, `created_at`, `last_login`) VALUES
(1, 'admin', '$2y$10$YXnKgqLHwUkh9HrpPYjQkeJqj0q3ANOW8HMdj4GJQZOiW.JiEETVO', 'Administrator', 'super_admin', '2025-05-17 21:54:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `candidates`
--

CREATE TABLE `candidates` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `college_code` varchar(10) NOT NULL,
  `position` varchar(50) NOT NULL,
  `party` varchar(50) DEFAULT NULL,
  `position_id` int(11) NOT NULL,
  `party_id` int(11) DEFAULT NULL,
  `platform` text DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidates`
--

INSERT INTO `candidates` (`id`, `student_id`, `name`, `college_code`, `position`, `party`, `position_id`, `party_id`, `platform`, `photo_url`, `created_at`, `updated_at`) VALUES
(45, 0, 'larry denver', 'sr', 'Senator', 'TDA', 9, 1, '', 'candidate_682b65b5b7b52.jpeg', '2025-05-20 01:09:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `elections`
--

CREATE TABLE `elections` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `election_settings`
--

CREATE TABLE `election_settings` (
  `id` int(11) NOT NULL,
  `election_title` varchar(255) NOT NULL,
  `election_start` datetime NOT NULL,
  `election_end` datetime NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `results_released` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parties`
--

CREATE TABLE `parties` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parties`
--

INSERT INTO `parties` (`id`, `name`, `description`, `logo_url`) VALUES
(1, 'TDA', 'TDA Party', NULL),
(2, 'INDEPENDENT', 'INDEPENDENT Party', NULL),
(3, 'IROLES', 'IROLES Party', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `positions`
--

CREATE TABLE `positions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `max_candidates` int(11) NOT NULL DEFAULT 1,
  `max_votes` int(11) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `positions`
--

INSERT INTO `positions` (`id`, `name`, `max_candidates`, `max_votes`, `display_order`) VALUES
(1, 'President', 1, 1, 1),
(2, 'Vice President', 1, 1, 2),
(3, 'Secretary', 1, 1, 3),
(4, 'Treasurer', 1, 1, 4),
(5, 'Auditor', 1, 1, 5),
(6, 'Public Information Officer', 1, 1, 6),
(7, 'Representative', 5, 5, 7),
(8, 'Governor', 1, 1, 0),
(9, 'Senator', 1, 1, 0),
(10, 'Vice Governor', 1, 1, 2);

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `college_code` varchar(10) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_republic_voters`
--

CREATE TABLE `student_republic_voters` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `registered_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `votes`
--

CREATE TABLE `votes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vote_details`
--

CREATE TABLE `vote_details` (
  `id` int(11) NOT NULL,
  `vote_id` int(11) NOT NULL,
  `position_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `voting_schedule`
--

CREATE TABLE `voting_schedule` (
  `id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `voting_schedule`
--

INSERT INTO `voting_schedule` (`id`, `start_time`, `end_time`, `is_active`, `created_at`, `updated_at`) VALUES
(1, '2025-05-19 08:00:00', '2025-05-26 17:00:00', 0, '2025-05-19 15:59:59', '2025-05-19 16:02:27'),
(2, '2025-05-20 00:00:00', '2025-05-20 00:04:00', 0, '2025-05-19 16:02:27', '2025-05-19 16:03:42'),
(3, '2025-05-19 00:00:00', '2025-05-19 00:05:00', 0, '2025-05-19 16:03:42', '2025-05-19 16:04:22'),
(4, '2025-05-19 08:00:00', '2025-05-20 00:30:00', 0, '2025-05-19 16:04:22', '2025-05-19 16:04:24'),
(5, '2025-05-19 08:00:00', '2025-05-26 17:00:00', 0, '2025-05-19 16:04:24', '2025-05-19 16:04:36'),
(6, '2025-05-19 08:00:00', '2025-05-20 17:00:00', 0, '2025-05-19 16:04:36', '2025-05-19 16:04:47'),
(7, '2025-05-19 08:00:00', '2025-05-20 00:30:00', 0, '2025-05-19 16:04:47', '2025-05-19 16:35:38'),
(8, '2025-05-20 00:00:00', '2025-05-20 00:41:00', 0, '2025-05-19 16:35:38', '2025-05-19 16:41:40'),
(9, '2025-05-20 00:00:00', '2025-05-20 00:41:00', 0, '2025-05-19 16:41:40', '2025-05-19 18:13:40'),
(10, '2025-05-19 08:00:00', '2025-05-26 17:00:00', 1, '2025-05-19 18:13:40', '2025-05-19 18:13:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `candidates`
--
ALTER TABLE `candidates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `party_id` (`party_id`);

--
-- Indexes for table `elections`
--
ALTER TABLE `elections`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `election_settings`
--
ALTER TABLE `election_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `parties`
--
ALTER TABLE `parties`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `positions`
--
ALTER TABLE `positions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`,`college_code`);

--
-- Indexes for table `student_republic_voters`
--
ALTER TABLE `student_republic_voters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `votes`
--
ALTER TABLE `votes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `election_id` (`election_id`);

--
-- Indexes for table `vote_details`
--
ALTER TABLE `vote_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vote_id` (`vote_id`),
  ADD KEY `position_id` (`position_id`),
  ADD KEY `candidate_id` (`candidate_id`);

--
-- Indexes for table `voting_schedule`
--
ALTER TABLE `voting_schedule`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidates`
--
ALTER TABLE `candidates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `elections`
--
ALTER TABLE `elections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `election_settings`
--
ALTER TABLE `election_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `parties`
--
ALTER TABLE `parties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `positions`
--
ALTER TABLE `positions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `student_republic_voters`
--
ALTER TABLE `student_republic_voters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `votes`
--
ALTER TABLE `votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `vote_details`
--
ALTER TABLE `vote_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=65;

--
-- AUTO_INCREMENT for table `voting_schedule`
--
ALTER TABLE `voting_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `student_republic_voters`
--
ALTER TABLE `student_republic_voters`
  ADD CONSTRAINT `sr_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `votes`
--
ALTER TABLE `votes`
  ADD CONSTRAINT `vote_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vote_details`
--
ALTER TABLE `vote_details`
  ADD CONSTRAINT `vote_detail_candidate_fk` FOREIGN KEY (`candidate_id`) REFERENCES `candidates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vote_detail_vote_fk` FOREIGN KEY (`vote_id`) REFERENCES `votes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
