CREATE DATABASE IF NOT EXISTS MediFlow;
USE MediFlow;

-- 1. Patients
CREATE TABLE Patients (
    patient_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    age INT,
    gender VARCHAR(20),
    phone VARCHAR(20),
    disease VARCHAR(150),
    priority_level VARCHAR(20) DEFAULT 'Normal'
);

-- 2. Doctors
CREATE TABLE Doctors (
    doctor_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100),
    phone VARCHAR(20)
);

-- 3. Beds and Wards
CREATE TABLE Beds (
    bed_id INT PRIMARY KEY AUTO_INCREMENT,
    ward VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Available'
);

-- 4. Medicines
CREATE TABLE Medicines (
    medicine_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    quantity INT DEFAULT 0,
    reorder_level INT DEFAULT 10,
    expiry_date DATE
);

-- 5. Appointments
CREATE TABLE Appointments (
    appointment_id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT,
    doctor_id INT,
    appointment_date DATE,
    FOREIGN KEY (patient_id)
        REFERENCES Patients(patient_id),
    FOREIGN KEY (doctor_id)
        REFERENCES Doctors(doctor_id)
);

-- 6. Resource Demand Prediction
CREATE TABLE Demand_Predictions (
    prediction_id INT PRIMARY KEY AUTO_INCREMENT,
    resource_type VARCHAR(50),
    predicted_demand INT,
    prediction_date DATE
);

-- Sample data
INSERT INTO Doctors (name, specialization, phone)
VALUES
('Dr. Rahman', 'Cardiology', '01711111111'),
('Dr. Karim', 'Medicine', '01822222222');

INSERT INTO Beds (ward, status)
VALUES
('General Ward', 'Available'),
('ICU', 'Available'),
('General Ward', 'Occupied');

INSERT INTO Medicines
    (name, quantity, reorder_level, expiry_date)
VALUES
('Paracetamol', 100, 20, '2027-06-30'),
('Antibiotic', 5, 10, '2027-01-31');
