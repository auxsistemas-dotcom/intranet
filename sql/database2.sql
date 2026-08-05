-- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
--
-- Host: localhost    Database: intranet
-- ------------------------------------------------------
-- Server version	8.0.46

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `anuncios`
--

DROP TABLE IF EXISTS `anuncios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `anuncios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `anuncios_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anuncios`
--

LOCK TABLES `anuncios` WRITE;
/*!40000 ALTER TABLE `anuncios` DISABLE KEYS */;
INSERT INTO `anuncios` VALUES (1,'Trabajo en Casa Regional','Días en casa','anuncio_1784671643_6a5fed9b82bec.pdf',1,1,'2026-07-21 16:40:18'),(2,'Acta 2','Validar','anuncio_1784672240_6a5feff0d38e8.pdf',1,1,'2026-07-21 17:17:20');
/*!40000 ALTER TABLE `anuncios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_capacitaciones`
--

DROP TABLE IF EXISTS `categorias_capacitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_capacitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `tipo` enum('area','subarea','carpeta') DEFAULT 'area',
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `padre_id` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `padre_id` (`padre_id`),
  CONSTRAINT `categorias_capacitaciones_ibfk_1` FOREIGN KEY (`padre_id`) REFERENCES `categorias_capacitaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_capacitaciones`
--

LOCK TABLES `categorias_capacitaciones` WRITE;
/*!40000 ALTER TABLE `categorias_capacitaciones` DISABLE KEYS */;
INSERT INTO `categorias_capacitaciones` VALUES (1,'Inducción','Usuarios Nuevos','area',0,1,NULL,'2026-07-10 13:38:22'),(2,'Modulo 1','','carpeta',0,1,1,'2026-07-10 13:38:30'),(3,'Modulo 2','','carpeta',0,1,1,'2026-07-10 13:38:37'),(4,'Modulo 3','','carpeta',0,1,1,'2026-07-10 13:38:46');
/*!40000 ALTER TABLE `categorias_capacitaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_gestion_humana`
--

DROP TABLE IF EXISTS `categorias_gestion_humana`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categorias_gestion_humana` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text,
  `tipo` enum('area','subarea','carpeta') DEFAULT 'area',
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `padre_id` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `quiz_url` varchar(500) DEFAULT NULL COMMENT 'URL del formulario de quiz',
  PRIMARY KEY (`id`),
  KEY `padre_id` (`padre_id`),
  CONSTRAINT `categorias_gestion_humana_ibfk_1` FOREIGN KEY (`padre_id`) REFERENCES `categorias_gestion_humana` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_gestion_humana`
--

LOCK TABLES `categorias_gestion_humana` WRITE;
/*!40000 ALTER TABLE `categorias_gestion_humana` DISABLE KEYS */;
INSERT INTO `categorias_gestion_humana` VALUES (1,'Modulo 1','Sistemas - Contabilidad','area',0,1,NULL,'2026-07-14 10:06:52',NULL),(2,'Sistemas','','carpeta',0,1,1,'2026-07-14 10:07:07','https://docs.google.com/forms/d/e/1FAIpQLSfYWq9zhmby5voEMYrolgCVmiUup9s-neHia-SsWjJMIZgdHA/viewform?usp=header'),(3,'Contabilidad','','carpeta',0,1,1,'2026-07-14 10:07:19','https://docs.google.com/forms/d/e/1FAIpQLScSuxx4AnaL-ow0Rb64d4iww9zBiwWbw2PeR1as7XgJ8I6Iyg/viewform?usp=header');
/*!40000 ALTER TABLE `categorias_gestion_humana` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_capacitaciones`
--

DROP TABLE IF EXISTS `documentos_capacitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_capacitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categoria_id` int NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `tipo_archivo` enum('pdf','video','word','excel','link','imagen') DEFAULT 'pdf',
  `url` varchar(500) NOT NULL,
  `duracion_minutos` int DEFAULT NULL,
  `tamano_bytes` bigint DEFAULT NULL,
  `vistas` int DEFAULT '0',
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `idx_activo` (`activo`),
  KEY `documentos_capacitaciones_ibfk_2` (`creado_por`),
  CONSTRAINT `documentos_capacitaciones_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_capacitaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documentos_capacitaciones_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_capacitaciones`
--

LOCK TABLES `documentos_capacitaciones` WRITE;
/*!40000 ALTER TABLE `documentos_capacitaciones` DISABLE KEYS */;
INSERT INTO `documentos_capacitaciones` VALUES (2,2,'Introduccion TI','','pdf','uploads/documentos/capacitaciones/1783708779_6a513c6bbee23.pdf',NULL,NULL,0,0,1,1,'2026-07-10 13:39:39'),(3,2,'Introduccion TI 2','','pdf','uploads/documentos/capacitaciones/1783708795_6a513c7ba7ee0.pdf',NULL,NULL,0,0,1,1,'2026-07-10 13:39:55');
/*!40000 ALTER TABLE `documentos_capacitaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_gestion_humana`
--

DROP TABLE IF EXISTS `documentos_gestion_humana`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_gestion_humana` (
  `id` int NOT NULL AUTO_INCREMENT,
  `categoria_id` int NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `tipo_archivo` enum('pdf','video','word','excel','link','imagen') DEFAULT 'pdf',
  `url` varchar(500) NOT NULL,
  `duracion_minutos` int DEFAULT NULL,
  `tamano_bytes` bigint DEFAULT NULL,
  `vistas` int DEFAULT '0',
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `categoria_id` (`categoria_id`),
  KEY `idx_activo` (`activo`),
  KEY `documentos_gestion_humana_ibfk_2` (`creado_por`),
  CONSTRAINT `documentos_gestion_humana_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_gestion_humana` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documentos_gestion_humana_ibfk_2` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_gestion_humana`
--

LOCK TABLES `documentos_gestion_humana` WRITE;
/*!40000 ALTER TABLE `documentos_gestion_humana` DISABLE KEYS */;
INSERT INTO `documentos_gestion_humana` VALUES (1,3,'Manual 1','','pdf','uploads/documentos/gestion_humana/1784044448_6a565ba016f2c.pdf',NULL,NULL,0,0,1,1,'2026-07-14 10:54:08'),(2,2,'Acta 1','','pdf','uploads/documentos/gestion_humana/1784056041_6a5688e9d66b9.pdf',NULL,NULL,0,0,1,1,'2026-07-14 14:07:21'),(3,2,'Acta 2','','pdf','uploads/documentos/gestion_humana/1784056054_6a5688f6a3697.pdf',NULL,NULL,0,0,1,1,'2026-07-14 14:07:34'),(4,2,'Acta 3','','pdf','uploads/documentos/gestion_humana/1784056070_6a5689061bd1b.pdf',NULL,NULL,0,0,1,1,'2026-07-14 14:07:50');
/*!40000 ALTER TABLE `documentos_gestion_humana` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documentos_sig`
--

DROP TABLE IF EXISTS `documentos_sig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `documentos_sig` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text,
  `archivo_url` varchar(500) NOT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `documentos_sig_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documentos_sig`
--

LOCK TABLES `documentos_sig` WRITE;
/*!40000 ALTER TABLE `documentos_sig` DISABLE KEYS */;
/*!40000 ALTER TABLE `documentos_sig` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `logistica`
--

DROP TABLE IF EXISTS `logistica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `logistica` (
  `id` int NOT NULL AUTO_INCREMENT,
  `marca` varchar(50) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text,
  `archivo_url` varchar(500) NOT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `marca` (`marca`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `logistica_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `logistica`
--

LOCK TABLES `logistica` WRITE;
/*!40000 ALTER TABLE `logistica` DISABLE KEYS */;
INSERT INTO `logistica` VALUES (1,'kia','Inventario KIA','','uploads/logistica/1783624456_6a4ff308bd40e.xls',0,1,1,'2026-07-09 14:14:16'),(2,'honda','Inventario HONDA','','uploads/logistica/1783624538_6a4ff35a0c89b.xls',0,1,1,'2026-07-09 14:15:38');
/*!40000 ALTER TABLE `logistica` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `modulos`
--

DROP TABLE IF EXISTS `modulos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `modulos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL COMMENT 'Nombre que aparece en el menú',
  `ruta` varchar(100) NOT NULL COMMENT 'URL del módulo',
  `activo` tinyint DEFAULT '1' COMMENT '1=visible, 0=oculto',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `modulos`
--

LOCK TABLES `modulos` WRITE;
/*!40000 ALTER TABLE `modulos` DISABLE KEYS */;
INSERT INTO `modulos` VALUES (1,'Gestionar Pagina','/gestionar_pagina',1,'2026-07-01 15:45:12'),(2,'Usuarios','/usuarios',1,'2026-07-01 15:45:12'),(3,'Permisos','/permisos',1,'2026-07-01 15:45:12'),(4,'Capacitaciones','/capacitaciones',1,'2026-07-01 15:45:12'),(5,'Politicas','/politicas',1,'2026-07-01 15:45:12'),(6,'Documentos Sig','/documentos-sig',1,'2026-07-01 15:45:12'),(7,'Logistica','/logistica',1,'2026-07-01 15:45:12'),(8,'Gestion Humana','/gestion_humana',1,'2026-07-17 11:05:33');
/*!40000 ALTER TABLE `modulos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos_capacitaciones`
--

DROP TABLE IF EXISTS `permisos_capacitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permisos_capacitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL COMMENT 'ID del usuario que recibe el permiso',
  `categoria_id` int NOT NULL COMMENT 'ID del área o carpeta en categorias_capacitaciones',
  `tipo` enum('area','carpeta') DEFAULT 'area' COMMENT 'Tipo de elemento: area o carpeta',
  `puede_ver` tinyint DEFAULT '1' COMMENT '1=puede ver, 0=no puede ver',
  `fecha_asignacion` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de asignación del permiso',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_permiso` (`usuario_id`,`categoria_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `permisos_capacitaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permisos_capacitaciones_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_capacitaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos_capacitaciones`
--

LOCK TABLES `permisos_capacitaciones` WRITE;
/*!40000 ALTER TABLE `permisos_capacitaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `permisos_capacitaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permisos_usuarios`
--

DROP TABLE IF EXISTS `permisos_usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permisos_usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL COMMENT 'ID del usuario',
  `modulo_id` int NOT NULL COMMENT 'ID del módulo al que tiene acceso',
  `fecha_asignacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_modulo` (`usuario_id`,`modulo_id`),
  KEY `modulo_id` (`modulo_id`),
  CONSTRAINT `permisos_usuarios_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permisos_usuarios_ibfk_2` FOREIGN KEY (`modulo_id`) REFERENCES `modulos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=73 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permisos_usuarios`
--

LOCK TABLES `permisos_usuarios` WRITE;
/*!40000 ALTER TABLE `permisos_usuarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `permisos_usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `politicas`
--

DROP TABLE IF EXISTS `politicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `politicas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text,
  `archivo_url` varchar(500) NOT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `creado_por` int DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `politicas_armotor_ibfk_1` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `politicas`
--

LOCK TABLES `politicas` WRITE;
/*!40000 ALTER TABLE `politicas` DISABLE KEYS */;
INSERT INTO `politicas` VALUES (1,'Política sobre seguridad de la información','Saber gestionar, cuidar y garantizar la seguridad','uploads/documentos/politicas/1783366512_6a4c03704161e.pdf',0,1,1,'2026-07-06 14:35:12');
/*!40000 ALTER TABLE `politicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progreso_gestion_humana`
--

DROP TABLE IF EXISTS `progreso_gestion_humana`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `progreso_gestion_humana` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `documento_id` int NOT NULL,
  `visto` tinyint DEFAULT '1',
  `fecha_visto` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_documento` (`usuario_id`,`documento_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `documento_id` (`documento_id`),
  CONSTRAINT `progreso_gestion_humana_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progreso_gestion_humana_ibfk_2` FOREIGN KEY (`documento_id`) REFERENCES `documentos_gestion_humana` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progreso_gestion_humana`
--

LOCK TABLES `progreso_gestion_humana` WRITE;
/*!40000 ALTER TABLE `progreso_gestion_humana` DISABLE KEYS */;
INSERT INTO `progreso_gestion_humana` VALUES (64,2,2,1,'2026-07-16 12:02:07'),(65,2,3,1,'2026-07-16 12:02:16'),(66,2,4,1,'2026-07-16 12:02:20'),(67,2,1,1,'2026-07-16 12:02:39'),(68,4,2,1,'2026-07-21 09:31:06'),(69,4,3,1,'2026-07-21 09:31:18'),(70,4,4,1,'2026-07-21 09:31:22');
/*!40000 ALTER TABLE `progreso_gestion_humana` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progreso_usuarios_capacitaciones`
--

DROP TABLE IF EXISTS `progreso_usuarios_capacitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `progreso_usuarios_capacitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL COMMENT 'ID del usuario que ve el documento',
  `documento_id` int NOT NULL COMMENT 'ID del documento visto',
  `visto` tinyint DEFAULT '1' COMMENT '1=visto, 0=no visto',
  `fecha_visto` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha en que se vio',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_documento` (`usuario_id`,`documento_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `documento_id` (`documento_id`),
  CONSTRAINT `progreso_usuarios_capacitaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `progreso_usuarios_capacitaciones_ibfk_2` FOREIGN KEY (`documento_id`) REFERENCES `documentos_capacitaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progreso_usuarios_capacitaciones`
--

LOCK TABLES `progreso_usuarios_capacitaciones` WRITE;
/*!40000 ALTER TABLE `progreso_usuarios_capacitaciones` DISABLE KEYS */;
INSERT INTO `progreso_usuarios_capacitaciones` VALUES (7,1,2,1,'2026-07-10 14:39:23'),(9,1,3,1,'2026-07-10 14:50:10');
/*!40000 ALTER TABLE `progreso_usuarios_capacitaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_gestion_humana`
--

DROP TABLE IF EXISTS `quiz_gestion_humana`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_gestion_humana` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `categoria_id` int NOT NULL COMMENT 'ID de la carpeta',
  `completado` tinyint DEFAULT '0' COMMENT '0=pendiente, 1=completado',
  `fecha_completado` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario_categoria` (`usuario_id`,`categoria_id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `quiz_gestion_humana_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_gestion_humana_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_gestion_humana` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_gestion_humana`
--

LOCK TABLES `quiz_gestion_humana` WRITE;
/*!40000 ALTER TABLE `quiz_gestion_humana` DISABLE KEYS */;
INSERT INTO `quiz_gestion_humana` VALUES (8,2,2,1,'2026-07-16 12:02:27'),(9,2,3,1,'2026-07-16 12:02:48'),(10,4,2,1,'2026-07-21 09:31:28');
/*!40000 ALTER TABLE `quiz_gestion_humana` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Acceso total a todos los módulos',1,'2026-07-01 15:22:30'),(2,'Supervisor','Acceso a módulos asignados por el admin',1,'2026-07-01 15:22:30'),(3,'Usuario','Sin acceso al panel administrativo',1,'2026-07-01 15:22:30');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `slider`
--

DROP TABLE IF EXISTS `slider`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `slider` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(100) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL COMMENT 'Subtítulo o descripción',
  `imagen_url` varchar(500) NOT NULL,
  `tipo` enum('noticia','promocion','aviso','evento') DEFAULT 'noticia',
  `enlace` varchar(500) DEFAULT NULL,
  `orden` int DEFAULT '0',
  `activo` tinyint DEFAULT '1',
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `creado_por` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_tipo` (`tipo`),
  KEY `creado_por` (`creado_por`),
  CONSTRAINT `slider_creado_por_fk` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `slider`
--

LOCK TABLES `slider` WRITE;
/*!40000 ALTER TABLE `slider` DISABLE KEYS */;
INSERT INTO `slider` VALUES (1,'Innovación que Mueve tu Negocio','Tecnología al servicio de tu productividad','https://www.elcarrocolombiano.com/wp-content/uploads/2026/04/20260403-KIA-SELTOS-2027-ESTADOS-UNIDOS-01.jpg','noticia',NULL,4,1,'2026-07-01 15:50:23',NULL),(2,'Conectividad Inteligente','Todas tus herramientas en un solo lugar','https://cdn-images.motor.es/image/m/1320w/fotos-noticias/2026/06/precio-honda-cr-v-2026-2026114494-1780304381_1.jpg','promocion',NULL,2,1,'2026-07-01 15:50:23',NULL),(3,'Gestión Eficiente','Optimiza tus procesos diarios','https://www.elcarrocolombiano.com/wp-content/uploads/2025/11/20251119-FAW-TRUCKS-LION-TIGER-COLOMBIA-PORTADA.jpg','aviso',NULL,3,1,'2026-07-01 15:50:23',NULL),(4,'Nueva Honda','Nuevo VH para tu familia','uploads/slider/1782940835_6a4584a37cb26.jpg','noticia','',1,1,'2026-07-01 16:20:35',1);
/*!40000 ALTER TABLE `slider` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL COMMENT 'Número de celular',
  `perfil_usuario` varchar(100) DEFAULT NULL COMMENT 'Perfil del usuario (Ej: Asesor)',
  `cargo` varchar(100) DEFAULT NULL COMMENT 'Cargo del usuario (Ej: Asesor comercial)',
  `sede` varchar(100) DEFAULT NULL COMMENT 'Sede donde trabaja (Ej: Manizales)',
  `password` varchar(255) NOT NULL,
  `rol_id` int DEFAULT '3' COMMENT '1=admin, 2=supervisor, 3=usuario',
  `activo` tinyint DEFAULT '1',
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `rol_id` (`rol_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Administrador','admin','admin@intranet.com',NULL,NULL,NULL,NULL,'$2y$10$LBuiVIsY1dcyPzBsBffBVeEP6dVl4c7H4gvNngQ4y/cTuzaTVYGKe',1,1,'2026-07-01 15:39:28',NULL),(2,'Juan David Calvo','jcalvo','jcalvo@armotor.com','','Administrativo','Auxiliar Contable','Manizales','$2y$10$8tDHAWMwnv3JkPduTeuu.eGGdOB6HsVFPGCJ.QvLSgVVwBHOVThbK',3,1,'2026-07-01 15:39:28',NULL),(3,'Edward Zapata','ezapata','ezapata@armotor.com','','Líder / Coordinador / Jefatura','Líder de Sistemas','Manizales','$2y$10$2CKOETSTaIB0b4UkW/HbIOA4Sjo7GLxLxR8GFPMrOvlFASj1Fqo1.',2,1,'2026-07-01 15:39:28',NULL),(4,'Andres Lopez','alopez','','','Administrativo','Auxiliar Sistemas','Manizales','$2y$10$VsHRglqJR1N8.i5Vhco08.HMUzXYQAEhSGsJWgfqNO1lK8smdSNLq',2,1,'2026-07-16 15:06:04',NULL),(5,'Laura Giraldo Vargas','dsierra','','','Administrativo','Asistente Gestión Humana','Manizales','$2y$10$hKHWKs1f1y.UiSDZfhe9gepth88SYH8VgHgHl2h97ep4UnCix3Zz6',2,1,'2026-07-17 10:22:48',NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios_permisos_capacitaciones`
--

DROP TABLE IF EXISTS `usuarios_permisos_capacitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `usuarios_permisos_capacitaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `fecha_asignacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_usuario` (`usuario_id`),
  CONSTRAINT `usuarios_permisos_capacitaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios_permisos_capacitaciones`
--

LOCK TABLES `usuarios_permisos_capacitaciones` WRITE;
/*!40000 ALTER TABLE `usuarios_permisos_capacitaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuarios_permisos_capacitaciones` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-22  8:34:04
