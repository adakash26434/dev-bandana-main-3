-- =====================================================
-- FIX MISSING COLUMNS - Emergency Fix for 500 Errors
-- Run this script to add missing database columns
-- =====================================================

-- Fix missing columns in various tables
-- These columns are referenced in queries but don't exist

-- Add missing columns to admin_users table if they don't exist
ALTER TABLE admin_users 
ADD COLUMN IF NOT EXISTS full_name_np VARCHAR(255) DEFAULT NULL AFTER full_name,
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER is_active,
ADD COLUMN IF NOT EXISTS risk_review_status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER role;

-- Add missing columns to members table if they don't exist
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS full_name_np VARCHAR(255) DEFAULT NULL AFTER name,
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER is_active,
ADD COLUMN IF NOT EXISTS risk_review_status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER status;

-- Add missing columns to loan_applications table if they don't exist
ALTER TABLE loan_applications 
ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL AFTER member_id,
ADD COLUMN IF NOT EXISTS full_name_np VARCHAR(255) DEFAULT NULL AFTER full_name,
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER status,
ADD COLUMN IF NOT EXISTS risk_review_status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER status;

-- Add missing columns to kyc_applications table if they don't exist
ALTER TABLE kyc_applications 
ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL AFTER member_id,
ADD COLUMN IF NOT EXISTS full_name_np VARCHAR(255) DEFAULT NULL AFTER full_name,
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER status,
ADD COLUMN IF NOT EXISTS risk_review_status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER status;

-- Add missing columns to news table if they don't exist
ALTER TABLE news 
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER is_active;

-- Add missing columns to notices table if they don't exist
ALTER TABLE notices 
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER is_active;

-- Add missing columns to committees table if they don't exist
ALTER TABLE committees 
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER is_active;

-- Add missing columns to careers table if they don't exist
ALTER TABLE careers 
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER is_active;

-- Add missing columns to digital_service_requests table if they don't exist
ALTER TABLE digital_service_requests 
ADD COLUMN IF NOT EXISTS full_name VARCHAR(255) DEFAULT NULL AFTER member_id,
ADD COLUMN IF NOT EXISTS full_name_np VARCHAR(255) DEFAULT NULL AFTER full_name,
ADD COLUMN IF NOT EXISTS published TINYINT(1) DEFAULT 1 AFTER status,
ADD COLUMN IF NOT EXISTS risk_review_status ENUM('pending','approved','rejected') DEFAULT 'pending' AFTER status;

-- Update existing records to have default values
UPDATE admin_users SET published = 1 WHERE published IS NULL;
UPDATE admin_users SET risk_review_status = 'approved' WHERE risk_review_status IS NULL;

UPDATE members SET published = 1 WHERE published IS NULL;
UPDATE members SET risk_review_status = 'approved' WHERE risk_review_status IS NULL;

UPDATE loan_applications SET published = 1 WHERE published IS NULL;
UPDATE loan_applications SET risk_review_status = 'pending' WHERE risk_review_status IS NULL;

UPDATE kyc_applications SET published = 1 WHERE published IS NULL;
UPDATE kyc_applications SET risk_review_status = 'pending' WHERE risk_review_status IS NULL;

UPDATE news SET published = 1 WHERE published IS NULL;
UPDATE notices SET published = 1 WHERE published IS NULL;
UPDATE committees SET published = 1 WHERE published IS NULL;
UPDATE careers SET published = 1 WHERE published IS NULL;

UPDATE digital_service_requests SET published = 1 WHERE published IS NULL;
UPDATE digital_service_requests SET risk_review_status = 'pending' WHERE risk_review_status IS NULL;
