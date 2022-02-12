-- phpMyAdmin SQL Dump
-- version 4.9.5deb2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 11, 2022 at 07:32 PM
-- Server version: 10.3.29-MariaDB-0ubuntu0.20.04.1
-- PHP Version: 7.4.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

--
-- Database: `fa_saygoweb`
--

-- --------------------------------------------------------

--
-- Table structure for table `0_import_file`
--

CREATE TABLE `0_import_file` (
  `id` int(11) NOT NULL,
  `bank_id` int(11) NOT NULL,
  `dt_import` datetime DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `0_import_file_type`
--

CREATE TABLE `0_import_file_type` (
  `id` int(11) NOT NULL,
  `bank_id` int(11) NOT NULL,
  `columns` varchar(255) DEFAULT NULL,
  `hide` varchar(255) DEFAULT NULL,
  `date_field` varchar(32) DEFAULT NULL,
  `amount_field` varchar(32) DEFAULT NULL,
  `date_format` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `0_import_line`
--

CREATE TABLE `0_import_line` (
  `id` int(11) NOT NULL,
  `bank_id` int(11) NOT NULL,
  `party_field` varchar(128) DEFAULT NULL,
  `party_match` varchar(128) DEFAULT NULL,
  `party_code` varchar(128) DEFAULT NULL,
  `party_type` varchar(128) DEFAULT NULL,
  `doc_field` varchar(128) DEFAULT NULL,
  `doc_match` varchar(128) DEFAULT NULL,
  `doc_type` varchar(128) DEFAULT NULL,
  `party_id` varchar(128) DEFAULT NULL,
  `doc_item_id` varchar(128) DEFAULT NULL,
  `doc_code` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `0_import_file`
--
ALTER TABLE `0_import_file`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `file_name` (`file_name`);

--
-- Indexes for table `0_import_file_type`
--
ALTER TABLE `0_import_file_type`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `0_import_line`
--
ALTER TABLE `0_import_line`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_party` (`bank_id`,`party_match`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `0_import_file`
--
ALTER TABLE `0_import_file`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `0_import_file_type`
--
ALTER TABLE `0_import_file_type`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `0_import_line`
--
ALTER TABLE `0_import_line`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;
COMMIT;
