-- ============================================================
-- schema.sql
-- Inicialización de la base de datos para el sistema de sorteos.
-- Ejecutar en MySQL >= 5.7 / MariaDB >= 10.3
-- ============================================================

-- 1. Crear base de datos (si no existe)
CREATE DATABASE IF NOT EXISTS eventos_sorteo
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE eventos_sorteo;

-- 2. Tabla de registros de participantes por evento
CREATE TABLE IF NOT EXISTS event_registrations (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    event_slug VARCHAR(100) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    name       VARCHAR(150) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_event_email (event_slug, email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla de respuestas de usuarios por evento
CREATE TABLE IF NOT EXISTS user_answers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    event_slug      VARCHAR(100) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    question_id     INT NOT NULL,
    selected_answer VARCHAR(50) NOT NULL,
    is_correct      BOOLEAN NOT NULL,
    answered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_question_per_event (event_slug, email, question_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Índices adicionales para optimizar las consultas del sorteo
CREATE INDEX IF NOT EXISTS idx_reg_slug   ON event_registrations (event_slug);
CREATE INDEX IF NOT EXISTS idx_ans_slug   ON user_answers (event_slug);
CREATE INDEX IF NOT EXISTS idx_ans_correct ON user_answers (event_slug, is_correct);
