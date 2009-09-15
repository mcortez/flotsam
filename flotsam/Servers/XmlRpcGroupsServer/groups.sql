-- phpMyAdmin SQL Dump
-- version 2.6.4-pl3
-- http://www.phpmyadmin.net
-- 
-- Host: db819.perfora.net
-- Generation Time: Apr 18, 2009 at 05:56 PM
-- Server version: 4.0.27
-- PHP Version: 4.3.10-200.schlund.1
-- 
-- Database: `db281670270`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `osagent`
-- 

CREATE TABLE `osagent` (
  `AgentID` varchar(128) NOT NULL default '',
  `ActiveGroupID` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`AgentID`)
) TYPE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroup`
-- 

CREATE TABLE `osgroup` (
  `GroupID` varchar(128) NOT NULL default '',
  `Name` varchar(255) NOT NULL default '',
  `Charter` text NOT NULL,
  `InsigniaID` varchar(128) NOT NULL default '',
  `FounderID` varchar(128) NOT NULL default '',
  `MembershipFee` int(11) NOT NULL default '0',
  `OpenEnrollment` varchar(255) NOT NULL default '',
  `ShowInList` tinyint(1) NOT NULL default '0',
  `AllowPublish` tinyint(1) NOT NULL default '0',
  `MaturePublish` tinyint(1) NOT NULL default '0',
  `OwnerRoleID` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`GroupID`),
  UNIQUE KEY `Name` (`Name`),
  FULLTEXT KEY `Name_2` (`Name`)
) TYPE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroupinvite`
-- 

CREATE TABLE `osgroupinvite` (
  `InviteID` varchar(128) NOT NULL default '',
  `GroupID` varchar(128) NOT NULL default '',
  `RoleID` varchar(128) NOT NULL default '',
  `AgentID` varchar(128) NOT NULL default '',
  `TMStamp` timestamp(14) NOT NULL,
  PRIMARY KEY  (`InviteID`),
  UNIQUE KEY `GroupID` (`GroupID`,`RoleID`,`AgentID`)
) TYPE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroupmembership`
-- 

CREATE TABLE `osgroupmembership` (
  `GroupID` varchar(128) NOT NULL default '',
  `AgentID` varchar(128) NOT NULL default '',
  `SelectedRoleID` varchar(128) NOT NULL default '',
  `Contribution` int(11) NOT NULL default '0',
  `ListInProfile` int(11) NOT NULL default '1',
  `AcceptNotices` int(11) NOT NULL default '1',
  PRIMARY KEY  (`GroupID`,`AgentID`)
) TYPE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroupnotice`
-- 

CREATE TABLE `osgroupnotice` (
  `GroupID` varchar(128) NOT NULL default '',
  `NoticeID` varchar(128) NOT NULL default '',
  `Timestamp` int(10) unsigned NOT NULL default '0',
  `FromName` varchar(255) NOT NULL default '',
  `Subject` varchar(255) NOT NULL default '',
  `Message` text NOT NULL,
  `BinaryBucket` text NOT NULL,
  PRIMARY KEY  (`GroupID`,`NoticeID`),
  KEY `Timestamp` (`Timestamp`)
) TYPE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgrouprolemembership`
-- 

CREATE TABLE `osgrouprolemembership` (
  `GroupID` varchar(128) NOT NULL default '',
  `RoleID` varchar(128) NOT NULL default '',
  `AgentID` varchar(128) NOT NULL default '',
  PRIMARY KEY  (`GroupID`,`RoleID`,`AgentID`)
) TYPE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osrole`
-- 

CREATE TABLE `osrole` (
  `GroupID` varchar(128) NOT NULL default '',
  `RoleID` varchar(128) NOT NULL default '',
  `Name` varchar(255) NOT NULL default '',
  `Description` varchar(255) NOT NULL default '',
  `Title` varchar(255) NOT NULL default '',
  `Powers` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`GroupID`,`RoleID`)
) TYPE=MyISAM;
