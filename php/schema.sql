-- =========================================================
-- SSR & Transcriptomics Database Schema
-- Run this in phpMyAdmin (or `mysql -u root -p < schema.sql`)
-- Adjust column names/types once your real data format is finalized.
-- =========================================================

CREATE DATABASE IF NOT EXISTS ssr_transcriptomics_db;
USE ssr_transcriptomics_db;

-- ---------------------------------------------------------
-- Site images managed from the admin page
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_images (
    slot            VARCHAR(60) PRIMARY KEY,
    image_path      VARCHAR(255) NOT NULL,
    alt_text        VARCHAR(255) NOT NULL,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Site appearance settings managed from the admin page
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key   VARCHAR(60) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO site_settings (setting_key, setting_value) VALUES
    ('palette', 'botanical'),
    ('font_family', 'manrope'),
    ('font_scale', 'normal')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

-- ---------------------------------------------------------
-- Table 1: SSR (Simple Sequence Repeat) markers
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS ssr_markers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    species         VARCHAR(150) NOT NULL,
    gene_id         VARCHAR(100),
    chromosome      VARCHAR(50),
    motif           VARCHAR(50),        -- e.g. "AT", "CAG"
    repeat_count    INT,
    start_pos       INT,
    end_pos         INT,
    forward_primer  VARCHAR(100),
    reverse_primer  VARCHAR(100),
    source          VARCHAR(150),       -- e.g. "NCBI", "in-house"
    INDEX idx_species (species),
    INDEX idx_gene (gene_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Table 2: Transcriptomics records
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS transcriptomics (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    species          VARCHAR(150) NOT NULL,
    gene_id          VARCHAR(100),
    tissue           VARCHAR(100),      -- e.g. "leaf", "root"
    condition_name   VARCHAR(150),      -- e.g. "drought stress"
    expression_value DECIMAL(10,4),     -- e.g. FPKM/TPM value
    sequence         TEXT,
    source           VARCHAR(150),
    INDEX idx_species2 (species),
    INDEX idx_gene2 (gene_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- A few sample rows so you can test the search page immediately
-- ---------------------------------------------------------
INSERT INTO ssr_markers (species, gene_id, chromosome, motif, repeat_count, start_pos, end_pos, forward_primer, reverse_primer, source)
VALUES
('Oryza sativa', 'OsGene001', 'Chr1', 'AT', 12, 1000, 1024, 'ATGCGTACGTAG', 'TTGGCATCGATT', 'NCBI'),
('Oryza sativa', 'OsGene045', 'Chr3', 'CAG', 8, 45210, 45234, 'GGCATTCGATAC', 'CCTTAGCGTAAG', 'NCBI'),
('Zea mays', 'ZmGene112', 'Chr5', 'GA', 15, 78012, 78042, 'AACGGTTACGGA', 'TTGCATGGCAAT', 'in-house');

INSERT INTO transcriptomics (species, gene_id, tissue, condition_name, expression_value, sequence, source)
VALUES
('Oryza sativa', 'OsGene001', 'leaf', 'control', 12.4500, 'ATGCGATCGTAGCTAGCTAGGCTA', 'NCBI'),
('Oryza sativa', 'OsGene001', 'leaf', 'drought stress', 34.8700, 'ATGCGATCGTAGCTAGCTAGGCTA', 'NCBI'),
('Zea mays', 'ZmGene112', 'root', 'control', 8.1200, 'TTGACGGATCCGATCGTAGCTTAG', 'in-house');
