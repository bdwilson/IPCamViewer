-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Mar 07, 2015 at 10:15 PM
-- Server version: 5.5.41-0ubuntu0.14.04.1
-- PHP Version: 5.5.9-1ubuntu4.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `cam`
--

-- --------------------------------------------------------

--
-- Table structure for table `cameras`
--

CREATE TABLE IF NOT EXISTS `cameras` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `location` varchar(12) NOT NULL,
  `enabled` int(11) NOT NULL DEFAULT '1',
  `snapshot_url` varchar(200) NOT NULL,
  `ignore_ranges` varchar(100) NOT NULL,
  `ignoreHome` int(11) NOT NULL,
  `ignoreAway` int(11) NOT NULL,
  `pirName` varchar(30) NOT NULL,
  `pirTime` datetime NOT NULL,
  `isAmcrest` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`cid`),
  UNIQUE KEY `name` (`location`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE IF NOT EXISTS `images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(500) NOT NULL,
  `date` datetime NOT NULL,
  `location` varchar(25) NOT NULL,
  `notified` int(11) NOT NULL DEFAULT '0',
  `eventId` int(11) DEFAULT NULL,
  PRIMARY KEY (`image`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `image` (`image`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=14221 ;

-- --------------------------------------------------------

--
-- Table structure for table `suppress`
--

CREATE TABLE IF NOT EXISTS `suppress` (
  `authkey` varchar(15) NOT NULL,
  `expiration` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(12) NOT NULL,
  `authkey` varchar(20) NOT NULL,
  `enabled` int(1) NOT NULL DEFAULT '1',
  `admin` int(11) NOT NULL DEFAULT '0',
  `week` int(1) NOT NULL DEFAULT '1',
  `pushoverApp` varchar(30) NOT NULL,
  `pushoverKey` varchar(30) NOT NULL,
  `lastNotify` datetime NOT NULL,
  `isHome` int(11) DEFAULT '0',
  `homeTime` datetime NOT NULL,
  UNIQUE KEY `uid` (`uid`),
  UNIQUE KEY `user` (`user`),
  KEY `uid_2` (`uid`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=3 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
