-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: erxv1bzckceve5lh.cbetxkdyhwsb.us-east-1.rds.amazonaws.com    Database: zghm67erntjc1fv1
-- ------------------------------------------------------
-- Server version	8.0.42

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `evenements`
--

DROP TABLE IF EXISTS `evenements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `evenements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `description` text COLLATE utf8mb4_general_ci NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `visibilite` enum('visible','invisible') COLLATE utf8mb4_general_ci NOT NULL,
  `nb_joueurs` int NOT NULL,
  `organisateur_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `organisateur_id` (`organisateur_id`),
  CONSTRAINT `evenements_ibfk_1` FOREIGN KEY (`organisateur_id`) REFERENCES `utilisateurs` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `evenements`
--

LOCK TABLES `evenements` WRITE;
/*!40000 ALTER TABLE `evenements` DISABLE KEYS */;
INSERT INTO `evenements` VALUES (1,'Tournoi Fortnite','Demi-finale du Tournoi Fortnite','2026-01-10 09:00:00','2026-01-10 10:00:00','visible',4,1),(2,'Tournoi League of Legend','Tournoi départemental League of Legend','2026-01-17 14:00:00','2026-01-17 15:00:00','visible',8,1),(3,'Tournoi Counter-Strike','Finale du tournoi Counter-Strike','2026-01-24 09:00:00','2026-01-24 10:00:00','visible',2,1),(4,'Tournoi Fortnite','Compétition amicale de Fortnite avec des prix pour les meilleurs joueurs.','2026-01-31 14:00:00','2026-01-31 15:00:00','visible',16,1),(5,'Tournoi Counter-Strike','Compétition amicale de Counter-Strike avec des prix pour les meilleurs joueurs.','2026-02-07 09:00:00','2026-02-07 10:00:00','visible',16,1),(6,'Tournoi Valorant','Compétition amicale de Valorant avec des prix pour les meilleurs joueurs.','2026-02-14 14:00:00','2026-02-14 15:00:00','visible',16,1),(7,'Tournoi Overwatch 2','Compétition amicale de Overwatch 2 avec des prix pour les meilleurs joueurs.','2026-02-21 09:00:00','2026-02-21 10:00:00','visible',16,1),(8,'Tournoi Rocket League','Compétition amicale de Rocket League avec des prix pour les meilleurs joueurs.','2026-02-28 14:00:00','2026-02-28 15:00:00','visible',16,1),(9,'Tournoi Call of Duty','Compétition amicale de Call of Duty avec des prix pour les meilleurs joueurs.','2026-03-07 09:00:00','2026-03-07 10:00:00','visible',16,1),(10,'Tournoi Rainbow Six','Compétition amicale de Rainbow Six avec des prix pour les meilleurs joueurs.','2026-03-14 14:00:00','2026-03-14 15:00:00','visible',16,1);
/*!40000 ALTER TABLE `evenements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `participants`
--

DROP TABLE IF EXISTS `participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `participants` (
  `user_id` int NOT NULL,
  `event_id` int NOT NULL,
  KEY `user_id` (`user_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `participants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `utilisateurs` (`id`),
  CONSTRAINT `participants_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `evenements` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `participants`
--

LOCK TABLES `participants` WRITE;
/*!40000 ALTER TABLE `participants` DISABLE KEYS */;
/*!40000 ALTER TABLE `participants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('joueur','organisateur','administrateur') COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('connected','not_connected') COLLATE utf8mb4_general_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `utilisateurs`
--

LOCK TABLES `utilisateurs` WRITE;
/*!40000 ALTER TABLE `utilisateurs` DISABLE KEYS */;
INSERT INTO `utilisateurs` VALUES (1,'maxime1148','lg26mk38Cn407j','administrateur','not_connected'),(2,'organisateur_mc','mn37ro78Da306b','organisateur','not_connected'),(3,'Player1148','zc22tt99Ec208q','joueur','not_connected');
/*!40000 ALTER TABLE `utilisateurs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-21 21:22:00
