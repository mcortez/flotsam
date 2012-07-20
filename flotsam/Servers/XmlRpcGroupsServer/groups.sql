-- 
-- Table structure for table `osagent`
-- 

CREATE TABLE `osagent` (
  `AgentID` char(36) NOT NULL default '',
  `ActiveGroupID` char(36) NOT NULL default '',
  PRIMARY KEY  (`AgentID`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroup`
-- 

CREATE TABLE `osgroup` (
  `GroupID` char(36) NOT NULL default '',
  `Name` varchar(255) NOT NULL default '',
  `Charter` text NOT NULL,
  `InsigniaID` char(36) NOT NULL default '',
  `FounderID` char(36) NOT NULL default '',
  `MembershipFee` int(11) NOT NULL default '0',
  `OpenEnrollment` varchar(255) NOT NULL default '',
  `ShowInList` tinyint(1) NOT NULL default '0',
  `AllowPublish` tinyint(1) NOT NULL default '0',
  `MaturePublish` tinyint(1) NOT NULL default '0',
  `OwnerRoleID` char(36) NOT NULL default '',
  PRIMARY KEY  (`GroupID`),
  UNIQUE KEY `Name` (`Name`),
  FULLTEXT KEY `Name_2` (`Name`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroupinvite`
-- 

CREATE TABLE `osgroupinvite` (
  `InviteID` char(36) NOT NULL default '',
  `GroupID` char(36) NOT NULL default '',
  `RoleID` char(36) NOT NULL default '',
  `AgentID` char(36) NOT NULL default '',
  `TMStamp` timestamp NOT NULL,
  PRIMARY KEY  (`InviteID`),
  UNIQUE KEY `GroupID` (`GroupID`,`RoleID`,`AgentID`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroupmembership`
-- 

CREATE TABLE `osgroupmembership` (
  `GroupID`char(36) NOT NULL default '',
  `AgentID` char(36) NOT NULL default '',
  `SelectedRoleID` char(36) NOT NULL default '',
  `Contribution` int(11) NOT NULL default '0',
  `ListInProfile` int(11) NOT NULL default '1',
  `AcceptNotices` int(11) NOT NULL default '1',
  PRIMARY KEY  (`GroupID`,`AgentID`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgroupnotice`
-- 

CREATE TABLE `osgroupnotice` (
  `GroupID` char(36) NOT NULL default '',
  `NoticeID` char(36) NOT NULL default '',
  `Timestamp` int(10) unsigned NOT NULL default '0',
  `FromName` varchar(255) NOT NULL default '',
  `Subject` varchar(255) NOT NULL default '',
  `Message` text NOT NULL,
  `BinaryBucket` text NOT NULL,
  PRIMARY KEY  (`GroupID`,`NoticeID`),
  KEY `Timestamp` (`Timestamp`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osgrouprolemembership`
-- 

CREATE TABLE `osgrouprolemembership` (
  `GroupID` char(36) NOT NULL default '',
  `RoleID` char(36) NOT NULL default '',
  `AgentID` char(36) NOT NULL default '',
  PRIMARY KEY  (`GroupID`,`RoleID`,`AgentID`)
) ENGINE=MyISAM;

-- --------------------------------------------------------

-- 
-- Table structure for table `osrole`
-- 

CREATE TABLE `osrole` (
  `GroupID` char(36) NOT NULL default '',
  `RoleID` char(36) NOT NULL default '',
  `Name` varchar(255) NOT NULL default '',
  `Description` varchar(255) NOT NULL default '',
  `Title` varchar(255) NOT NULL default '',
  `Powers` bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY  (`GroupID`,`RoleID`)
) ENGINE=MyISAM;
