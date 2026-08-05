# Resumen del proyecto Armotor

## Qué llevo en el proyecto

Este proyecto es una aplicación web en PHP con una estructura modular orientada a la administración y gestión de documentos, usuarios y contenidos para la empresa Armotor.

### Funcionalidades principales
- Sistema de autenticación y control de sesión.
- Panel administrativo para gestionar:
  - usuarios
  - slider de contenido
  - documentación
  - documentos SIG
  - logística
  - permisos y roles
  - políticas de Armotor
- Gestión de documentos y archivos por módulos.
- Estructura preparada para ampliar funciones en el futuro.

## Estado actual del proyecto

El proyecto tiene una base sólida con:
- estructura inicial del sistema lista
- módulos principales organizados por carpetas
- conexión a base de datos configurada
- tablas principales definidas para la gestión del contenido y permisos

Se encuentra en una etapa de desarrollo/organización, con funcionalidades básicas ya planteadas y listas para seguir mejorando.

## Base de datos utilizada

Nombre de la base de datos:
- armotor_digital

### Tablas principales

- usuarios
  - almacena los usuarios del sistema y sus roles.

- slider
  - gestiona imágenes y contenido mostrado en el carrusel principal.

- categorias_manuales
  - organiza áreas y carpetas para los manuales técnicos.

- documentos_manuales
  - guarda los documentos asociados a las categorías de manuales.

- manuales_tecnicos
  - almacena manuales técnicos independientes.

- politicas_armotor
  - guarda las políticas internas del proyecto o empresa.

- formatos_operativos
  - contiene formatos operativos del sistema.

- permisos_documentacion
  - administra permisos de visualización por usuario y módulo.

## Resumen general

Este proyecto está enfocado en centralizar información, documentos y permisos en un sistema administrativo fácil de mantener y ampliar.
