-- AstroTarot MariaDB Database Schema
-- Created for AstroTarot website migration from JSON to MariaDB

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS astrotarot_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_romanian_ci;

USE astrotarot_db;

-- Videos table
CREATE TABLE IF NOT EXISTS videos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    link VARCHAR(500) NOT NULL,
    description TEXT,
    published_date DATE NOT NULL,
    zodiac_sign VARCHAR(50),
    video_type ENUM('horoscope', 'general', 'tarot', 'mystic') DEFAULT 'general',
    featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_published_date (published_date),
    INDEX idx_zodiac_sign (zodiac_sign),
    INDEX idx_video_type (video_type),
    INDEX idx_featured (featured)
) ENGINE=InnoDB;

-- Zodiac signs table
CREATE TABLE IF NOT EXISTS zodiac_signs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    html_path VARCHAR(255) NOT NULL,
    element ENUM('fire', 'earth', 'air', 'water'),
    symbol VARCHAR(10),
    date_range VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_element (element)
) ENGINE=InnoDB;

-- Bookings table (for consultation appointments)
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    preferred_time VARCHAR(255) NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    consultation_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_consultation_date (consultation_date)
) ENGINE=InnoDB;

-- Tarot cards table (for the daily message feature)
CREATE TABLE IF NOT EXISTS tarot_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    image_filename VARCHAR(255) NOT NULL,
    card_type ENUM('major', 'minor') DEFAULT 'major',
    number INT,
    element ENUM('fire', 'earth', 'air', 'water'),
    keywords JSON,
    upright_meaning TEXT,
    reversed_meaning TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_card_type (card_type),
    INDEX idx_element (element),
    INDEX idx_number (number)
) ENGINE=InnoDB;

-- Daily messages table (for tracking daily tarot draws)
CREATE TABLE IF NOT EXISTS daily_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    card_id INT NOT NULL,
    message_date DATE NOT NULL UNIQUE,
    custom_message TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (card_id) REFERENCES tarot_cards(id) ON DELETE CASCADE,
    INDEX idx_message_date (message_date),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB;

-- Accounts table (user login and admin access)
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(150) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB;

INSERT INTO accounts (email, password_hash, name, role, status) VALUES
('admin@astrotarot.site', '$2y$10$uKbydI3dEjBO1GgWK0AUs.bLiHCMn/OJzJgwszG7/AptqkiOuJlyS', 'Admin Astro Tarot', 'admin', 'active');

-- Website settings table
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key)
) ENGINE=InnoDB;

-- Insert zodiac signs data
INSERT INTO zodiac_signs (name, html_path, element, symbol, date_range) VALUES
('Berbec', 'zodiac/berbec.html', 'fire', '♈', '21 martie - 19 aprilie'),
('Taur', 'zodiac/taur.html', 'earth', '♉', '20 aprilie - 20 mai'),
('Gemeni', 'zodiac/gemeni.html', 'air', '♊', '21 mai - 20 iunie'),
('Rac', 'zodiac/rac.html', 'water', '♋', '21 iunie - 22 iulie'),
('Leu', 'zodiac/leu.html', 'fire', '♌', '23 iulie - 22 august'),
('Fecioară', 'zodiac/fecioara.html', 'earth', '♍', '23 august - 22 septembrie'),
('Balanță', 'zodiac/balanta.html', 'air', '♎', '23 septembrie - 22 octombrie'),
('Scorpion', 'zodiac/scorpion.html', 'water', '♏', '23 octombrie - 21 noiembrie'),
('Săgetător', 'zodiac/sagetator.html', 'fire', '♐', '22 noiembrie - 21 decembrie'),
('Capricorn', 'zodiac/capricorn.html', 'earth', '♑', '22 decembrie - 19 ianuarie'),
('Vărsător', 'zodiac/varsator.html', 'air', '♒', '20 ianuarie - 18 februarie'),
('Pești', 'zodiac/pesti.html', 'water', '♓', '19 februarie - 20 martie');

-- Insert tarot cards data (Major Arcana)
INSERT INTO tarot_cards (name, description, image_filename, card_type, number, upright_meaning, reversed_meaning) VALUES
('Nebunul', 'Un nou început plin de potențial. Acționează cu curaj, dar fără a ignora riscurile.', 'Nebunul.png', 'major', 0, 'Nou început, spontaneitate, curaj, libertate', 'Risc, nebunie, impulsivitate, neglijență'),
('Magicianul', 'Ai toate resursele necesare pentru a reuși. Manifestă-ți dorințele cu încredere.', 'Magicianul.png', 'major', 1, 'Manifestare, putere, competență, voință', 'Manipulare, slăbiciune, incompetență, dorință neîmplinită'),
('MareaPreoteasa', 'Intuiția și misterul domină. Ai încredere în ceea ce simți, nu doar în ce vezi.', 'MareaPreoteasa.png', 'major', 2, 'Intuiție, inconștient, mister, înțelepciune interioară', 'Secrete, mistificare, intuiție blocată, confuzie'),
('Imparateasa', 'Abundență, creație și grijă. Este momentul să dezvolți și să hrănești ceva important.', 'Imparateasa.png', 'major', 3, 'Fertilitate, feminitate, natură, abundență', 'Infertilitate, dependență, blocare creativă, neglijare'),
('Imparatul', 'Ordine, control și autoritate. Construiește pe baze solide și stabilește reguli clare.', 'Imparatul.png', 'major', 4, 'Autoritate, structură, control, stabilitate', 'Tiranție, haos, instabilitate, slăbiciune'),
('Hierofantul', 'Tradiții și învățături. Caută ghidare în valori consacrate sau într-un mentor.', 'Hierofantul.png', 'major', 5, 'Tradiție, conformitate, învățături, instituții', 'Rebeliune, non-conformism, nouă abordare, libertate'),
('Indragostitii', 'Alegere importantă sau conexiune profundă. Urmează-ți inima, dar cu luciditate.', 'Indragostitii.png', 'major', 6, 'Iubire, armonie, relații, alegeri valoroase', 'Conflicte, dezechilibru, decizii greșite, singurătate'),
('Carul', 'Determinare și progres. Controlul și disciplina te vor duce la victorie.', 'Carul.png', 'major', 7, 'Victorie, control, determinare, voință', 'Eșec, lipsă de control, pasivitate, slăbiciune'),
('Puterea', 'Putere interioară și răbdare. Blândețea controlează mai mult decât forța brută.', 'Puterea.png', 'major', 8, 'Forță interioară, curaj, răbdare, control', 'Slăbiciune, lipsă de curaj, vulnerabilitate, auto-dubitare'),
('Spanzuratul', 'Pauză și perspectivă nouă. Uneori trebuie să renunți pentru a înțelege.', 'Spanzuratul.png', 'major', 12, 'Sacrificiu, nouă perspectivă, renunțare, înțelegere', 'Stagnare, rezistență la schimbare, egoism, lipsă de viziune'),
('Moartea', 'Transformare profundă. Un final necesar pentru a face loc unui nou început.', 'Moartea.png', 'major', 13, 'Transformare, sfârșit, nou început, schimbare', 'Rezistență la schimbare, teamă de pierdere, stagnare'),
('Temperanta', 'Echilibru și armonie. Combină lucrurile cu măsură pentru rezultate optime.', 'Temperanta.png', 'major', 14, 'Echilibru, moderare, armonie, răbdare', 'Dezechilibru, exces, conflicte, impatiență'),
('Diavolul', 'Atașamente și iluzii. Fii atent la dependențe sau la ce te ține blocat.', 'Diavolul.png', 'major', 15, 'Atașament, materialism, dependență, iluzii', 'Eliberare, spiritualitate, depășirea dependențelor, claritate'),
('Turnul', 'Schimbare bruscă și haos. Ce nu e stabil se va prăbuși pentru a face loc adevărului.', 'Turnul.png', 'major', 16, 'Schimbare bruscă, haos, revelație, adevăr', 'Evitarea schimbării, frică de haos, atașament de fals', 'Judecata', 'Trezire și claritate. Este timpul să îți evaluezi trecutul și să mergi mai departe.', 'Judecata.png', 'major', 20, 'Renaștere, trezire spirituală, judecată, iertare', 'Dificultăți în a trezi, auto-critică excesivă, blocaj'),
('Lumea', 'Finalizare și împlinire. Ai ajuns la capătul unui ciclu cu succes.', 'Lumea.png', 'major', 21, 'Împlinire, completare, succes, călătorie finalizată', 'Incompletitudine, eșec, lipsă de închidere, amânare'),
('Soarele', 'Bucurie absolută și claritate. Urmează o perioadă plină de succes și vitalitate.', 'Soarele.png', 'major', 19, 'Bucurie, succes, vitalitate, claritate', 'Pessimism, eșec, tristețe, negativitate'),
('Luna', 'Fii atent la intuiție și vise. Nu tot ce pare real este adevărat în acest moment.', 'Luna.png', 'major', 18, 'Intuiție, iluzie, inconștient, mister', 'Confuzie, teamă, anxietate, iluzii dezamăgitoare'),
('Steaua', 'Speranța este călăuza ta. Universul îți trimite vindecare și inspirație divină.', 'Steaua.png', 'major', 17, 'Speranță, vindecare, inspirație, credință', 'Disperare, lipsă de credință, dezamăgire, cinism'),
('Eremitul', 'Este timpul să te retragi în liniște. Răspunsul se află în interiorul tău, nu în afară.', 'Eremitul.png', 'major', 9, 'Introspecție, înțelepciune, singurătate, căutare interioară', 'Izolare, singurătate forțată, retragere excesivă, confuzie'),
('RoataNorocului', 'Schimbarea este singura constantă. Norocul este de partea ta, acceptă fluxul vieții.', 'RoataNorocului.png', 'major', 10, 'Schimbare, ciclicitate, destin, noroc', 'Rezistență la schimbare, ghinion, stagnare, ciclu negativ');

-- Insert sample videos data (from existing JSON)
INSERT INTO videos (title, link, description, published_date, zodiac_sign, video_type, featured) VALUES
('Capricorn Mai 2026', 'https://youtu.be/m3dJfepbPZ0?si=NRVHbiEZJVcu5ySh', 'ACEA SCLIPIRE DE MOMENT TE DUCE CĂTRE PERIOADA SUCCESELOR FINANCIARE IN FAMILIE💯🏡', '2026-04-21', 'Capricorn', 'horoscope', TRUE),
('ȘAMANUL MISTIC 👁️🌌🔮', 'https://youtu.be/aEo7r-HLy9g?si=3WNfmMZ7HKemZ1cC', 'BONUS SĂPTĂMÂNA 20-26 APRILIE 2026', '2026-04-20', NULL, 'mystic', TRUE),
('Sagetator Mai 2026', 'https://youtu.be/vI5_ZBSBxXc?si=10C5FiIo5pm4j7I-', 'RECUPEREZI TOT🌿STIMA DE SINE,PUBLICĂ SI SUFLETUL TĂU💯🏡', '2026-04-20', 'Săgetător', 'horoscope', FALSE),
('Scorpion Mai 2026', 'https://youtu.be/6nyffyMPHWE?si=d6SSOxkT6QvxSLfU', 'EȘTI PE LUNIA ÎNTÂI A ORIZONTULUI DE GÂNDIRE💯SOARELE🌞RĂSARE ÎN FINAL🍀', '2026-04-19', 'Scorpion', 'horoscope', FALSE);

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, description) VALUES
('site_title', 'Astro Tarot', 'Titlul principal al site-ului'),
('site_description', 'Ghidaj Cosmic · Lecturi Sacre · Înțelepciunea Stelelor', 'Descrierea site-ului'),
('contact_email', 'miuletdaniel@gmail.com', 'Email de contact principal'),
('youtube_channel', 'https://www.youtube.com/@astro_tarot10', 'Canalul YouTube'),
('currency', 'RON', 'Moneda folosită pentru servicii'),
('booking_status', 'open', 'Statusul programărilor (open/closed)');

-- Create a database user for the application
CREATE USER IF NOT EXISTS 'astrotarot_user'@'localhost' IDENTIFIED BY 'your_secure_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON astrotarot_db.* TO 'astrotarot_user'@'localhost';
FLUSH PRIVILEGES;
