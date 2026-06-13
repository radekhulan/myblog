-- ============================================================================
-- MyBlog — schéma potřebných tabulek (struktura BLOG:CMS / Nucleus CMS)
-- ----------------------------------------------------------------------------
-- CZ: Pro nasazení BEZ stávající Nucleus databáze vytvoř prázdnou databázi
--     a naimportuj tento soubor:   mysql -u root -p mojedb < install/nucleus.sql
--     Prefix tabulek (nucleus_) lze přejmenovat dle 'prefix' v cfg.php.
--     Admin účet a MEMORY tabulku doplní/ověří install/setup.php.
--
-- EN: To deploy WITHOUT an existing Nucleus database, create an empty database
--     and import this file:        mysql -u root -p mydb < install/nucleus.sql
--     The table prefix (nucleus_) can be renamed to match 'prefix' in cfg.php.
--     The admin account is created/verified by install/setup.php.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

/*M!999999\- enable the sandbox mode */ 
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_blog` (
  `bnumber` int(11) NOT NULL AUTO_INCREMENT,
  `bname` varchar(100) NOT NULL,
  `bshortname` varchar(15) NOT NULL,
  `bdesc` varchar(200) DEFAULT NULL,
  `bcomments` tinyint(2) NOT NULL DEFAULT 1,
  `bmaxcomments` int(11) NOT NULL DEFAULT 0,
  `btimeoffset` decimal(3,1) NOT NULL DEFAULT 0.0,
  `bnotify` varchar(60) DEFAULT NULL,
  `burl` varchar(100) DEFAULT NULL,
  `bupdate` varchar(60) DEFAULT NULL,
  `bdefskin` int(11) NOT NULL DEFAULT 1,
  `bpublic` tinyint(2) NOT NULL DEFAULT 1,
  `bsendping` tinyint(2) NOT NULL DEFAULT 0,
  `bconvertbreaks` tinyint(2) NOT NULL DEFAULT 1,
  `bdefcat` int(11) DEFAULT NULL,
  `bnotifytype` int(11) NOT NULL DEFAULT 15,
  `ballowpast` tinyint(2) NOT NULL DEFAULT 0,
  `bincludesearch` tinyint(2) NOT NULL DEFAULT 0,
  `bvip` tinyint(2) DEFAULT 0,
  PRIMARY KEY (`bnumber`) USING BTREE,
  UNIQUE KEY `bshortname` (`bshortname`) USING BTREE,
  KEY `bvip` (`bvip`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_category` (
  `catid` int(11) NOT NULL AUTO_INCREMENT,
  `cblog` int(11) NOT NULL DEFAULT 0,
  `cname` varchar(40) DEFAULT NULL,
  `iurltitle` varchar(40) DEFAULT NULL,
  `cdesc` varchar(200) DEFAULT NULL,
  `cgroup` int(11) DEFAULT 1,
  `cstring` varchar(1) DEFAULT NULL,
  `csort` int(11) DEFAULT 0,
  PRIMARY KEY (`catid`) USING BTREE,
  KEY `iurltitle` (`iurltitle`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_subcategory` (
  `blogid` int(11) DEFAULT NULL,
  `groupid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `shortname` varchar(60) DEFAULT NULL,
  `iurltitle` varchar(255) DEFAULT NULL,
  `subsort` int(11) DEFAULT 0,
  `sstring` varchar(1) DEFAULT NULL,
  PRIMARY KEY (`groupid`) USING BTREE,
  KEY `blogid` (`blogid`) USING BTREE,
  KEY `shortname` (`shortname`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Plugin: Subcategories';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_item` (
  `inumber` int(11) NOT NULL AUTO_INCREMENT,
  `ititle` varchar(160) DEFAULT NULL,
  `iurltitle` varchar(160) DEFAULT NULL,
  `ibody` text NOT NULL,
  `imore` text DEFAULT NULL,
  `iblog` int(11) NOT NULL DEFAULT 0,
  `iauthor` int(11) NOT NULL DEFAULT 0,
  `itime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `iclosed` tinyint(2) NOT NULL DEFAULT 0,
  `idraft` tinyint(2) NOT NULL DEFAULT 0,
  `ikarmapos` int(11) NOT NULL DEFAULT 0,
  `icat` int(11) DEFAULT NULL,
  `ikarmaneg` int(11) NOT NULL DEFAULT 0,
  `iexpiration` datetime NOT NULL DEFAULT '2060-01-01 00:00:00',
  `editor` int(2) DEFAULT 0,
  `ilatestcomment` datetime DEFAULT NULL,
  `inumcomments` int(11) DEFAULT 0,
  PRIMARY KEY (`inumber`) USING BTREE,
  KEY `itime` (`itime`) USING BTREE,
  KEY `iurltitle` (`iurltitle`) USING BTREE,
  KEY `icat` (`icat`) USING BTREE,
  KEY `ilatestcomment` (`ilatestcomment`) USING BTREE,
  KEY `blogdrafttime` (`iblog`,`idraft`,`itime`) USING BTREE,
  KEY `blogcat` (`iblog`,`icat`) USING BTREE,
  KEY `blogtime` (`iblog`,`itime`) USING BTREE,
  FULLTEXT KEY `issearch` (`ititle`,`ibody`,`imore`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_comment` (
  `cnumber` int(11) NOT NULL AUTO_INCREMENT,
  `cbody` text NOT NULL,
  `cuser` varchar(40) DEFAULT NULL,
  `cmail` varchar(100) DEFAULT NULL,
  `cmember` int(11) DEFAULT NULL,
  `citem` int(11) NOT NULL DEFAULT 0,
  `ctime` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `chost` varchar(60) DEFAULT NULL,
  `cip` varchar(15) NOT NULL,
  `cblog` int(11) NOT NULL DEFAULT 0,
  `cup` int(11) DEFAULT 0,
  `cdown` int(11) DEFAULT 0,
  PRIMARY KEY (`cnumber`) USING BTREE,
  KEY `citemtime` (`citem`,`ctime`) USING BTREE,
  KEY `cblogtime` (`cblog`,`ctime`) USING BTREE,
  KEY `ctime` (`ctime`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_member` (
  `mnumber` int(11) NOT NULL AUTO_INCREMENT,
  `mname` varchar(100) NOT NULL,
  `mrealname` varchar(200) DEFAULT NULL,
  `mjmeno` varchar(100) DEFAULT NULL,
  `mprijmeni` varchar(100) DEFAULT NULL,
  `mpassword` varchar(40) NOT NULL,
  `memail` varchar(60) DEFAULT NULL,
  `murl` varchar(100) DEFAULT NULL,
  `mnotes` varchar(100) DEFAULT NULL,
  `madmin` tinyint(2) NOT NULL DEFAULT 0,
  `mcanlogin` tinyint(2) NOT NULL DEFAULT 1,
  `mcookiekey` varchar(40) DEFAULT NULL,
  `mlastip` varchar(20) DEFAULT NULL,
  `mlastlogin` datetime DEFAULT NULL,
  `deflang` varchar(20) NOT NULL,
  `mpohlavi` tinyint(2) DEFAULT 0,
  `mfbuid` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`mnumber`) USING BTREE,
  UNIQUE KEY `mname` (`mname`) USING BTREE,
  KEY `memail` (`memail`) USING BTREE,
  KEY `mfbuid` (`mfbuid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_foto` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `fnazev` varchar(255) DEFAULT NULL,
  `fpopis` longtext DEFAULT NULL,
  `fdatum` datetime DEFAULT NULL,
  `fzmena` datetime DEFAULT NULL,
  `fkategorie` int(11) DEFAULT NULL,
  `ffotek` int(11) DEFAULT 0,
  `fviews` int(11) DEFAULT 0,
  `oid` int(11) DEFAULT 0,
  `fblog` int(11) DEFAULT NULL,
  `fhodnoceni` int(3) DEFAULT 0,
  `fitemid` int(11) DEFAULT 0,
  PRIMARY KEY (`fid`) USING BTREE,
  KEY `fdatum` (`fdatum`) USING BTREE,
  KEY `fnazev` (`fnazev`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fotogalerie - alba';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_foto_fotka` (
  `oid` int(11) NOT NULL AUTO_INCREMENT,
  `fid` int(11) NOT NULL,
  `onazev` varchar(255) DEFAULT NULL,
  `opopis` longtext DEFAULT NULL,
  `odatum` datetime DEFAULT NULL,
  `onahled` varchar(50) DEFAULT NULL,
  `osoubor` varchar(50) DEFAULT NULL,
  `oporadi` int(11) DEFAULT NULL,
  `okb` int(11) DEFAULT NULL,
  `ow` int(11) DEFAULT NULL,
  `oh` int(11) DEFAULT NULL,
  `otyp` smallint(6) DEFAULT 0,
  `oviews` int(11) DEFAULT 0,
  `ohodnoceni` int(3) DEFAULT 0,
  PRIMARY KEY (`oid`) USING BTREE,
  KEY `fid` (`fid`) USING BTREE,
  KEY `fidname` (`fid`,`onazev`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Fotogalerie - fotky';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_tags` (
  `tagid` int(11) NOT NULL AUTO_INCREMENT,
  `tagname` varchar(100) DEFAULT NULL,
  `tagused` datetime DEFAULT NULL,
  `tagcount` int(11) DEFAULT NULL,
  `tagblog` int(11) NOT NULL,
  `tagurl` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`tagid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_tags_item` (
  `titemid` int(11) NOT NULL,
  `ttagid` int(11) NOT NULL,
  `tcatid` int(11) DEFAULT NULL,
  PRIMARY KEY (`titemid`,`ttagid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nucleus_plugin_fancierurl` (
  `inumber` int(11) NOT NULL DEFAULT 0,
  `iurltitle` varchar(255) NOT NULL,
  PRIMARY KEY (`inumber`,`iurltitle`) USING BTREE,
  KEY `iurltitle` (`iurltitle`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

-- ---------------------------------------------------------------------------
-- Administrace MyBlog (jinak vytvoří/oseje install/setup.php).
-- Po importu spusť `php install/setup.php`, který založí výchozího admina.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `nucleus_myblog_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reset_token_hash` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `nucleus_myblog_loginfail` (
  `ip` varchar(45) NOT NULL,
  `fails` int(11) NOT NULL DEFAULT 0,
  `last_fail` datetime NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
