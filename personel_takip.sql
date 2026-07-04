CREATE DATABASE IF NOT EXISTS personel_takip;
USE personel_takip;

-- Personeller tablosu
CREATE TABLE IF NOT EXISTS personeller (
    id INT PRIMARY KEY,
    ad_soyad VARCHAR(100) NOT NULL,
    sifre VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE
);

-- Günlük kayıtlar tablosu
CREATE TABLE IF NOT EXISTS kayitlar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personel_id INT NOT NULL,
    tarih DATE NOT NULL,
    ise_gelis TIME DEFAULT NULL,
    molaya_cikis TIME DEFAULT NULL,
    moladan_donus TIME DEFAULT NULL,
    isten_cikis TIME DEFAULT NULL,
    FOREIGN KEY (personel_id) REFERENCES personeller(id) ON DELETE CASCADE
);