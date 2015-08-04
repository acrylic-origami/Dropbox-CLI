CREATE DATABASE /*!32312 IF NOT EXISTS*/ `dbcli` /*!40100 DEFAULT CHARACTER SET latin1 */;

USE `dbcli`;

--
-- Table structure for table `revs`
--

DROP TABLE IF EXISTS `revs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `revs` (
  `path` varchar(255) NOT NULL,
  `rev` tinyblob NOT NULL,
  `deleted_flag` tinyint(1) NOT NULL DEFAULT '0',
  `revision` int(11) NOT NULL,
  `edit_flag` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`path`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;