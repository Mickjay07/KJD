-- SQL příkazy pro vytvoření testovacích objednávek s různými jmény, emaily a telefony
-- KJD Admin System - Test Data

-- Vymazání existujících testovacích objednávek (pokud existují)
DELETE FROM orders WHERE order_id LIKE 'TEST-%';

-- Vytvoření testovacích objednávek
INSERT INTO orders (
    user_id, order_id, email, phone_number, name, delivery_method,
    zasilkovna_name, address, postal_code, total_price, status,
    tracking_code, note, products_json, is_preorder, release_date,
    payment_method, payment_status, shipping_cost, created_at
) VALUES 
-- Objednávka 1 - Jan Novák
(
    NULL, 'TEST-001', 'jan.novak@email.cz', '+420 123 456 789', 'Jan Novák',
    'standard', '', 'Václavské náměstí 1', '110 00', 1250.00, 'Přijato',
    'TRK001', 'Prosím o rychlé doručení', 
    '[{"id":"1","name":"Lampa Modern","quantity":1,"price":1250,"color":"Černá"}]',
    0, NULL, 'bank_transfer', 'pending', 90.00, NOW() - INTERVAL 5 DAY
),

-- Objednávka 2 - Marie Svobodová
(
    NULL, 'TEST-002', 'marie.svobodova@gmail.com', '777 888 999', 'Marie Svobodová',
    'zasilkovna', 'Zásilkovna Praha 1', 'Národní třída 25', '110 00', 890.00, 'Zpracovává se',
    'TRK002', 'Doručit do Zásilkovny', 
    '[{"id":"2","name":"Stolní lampa","quantity":1,"price":890,"color":"Bílá"}]',
    0, NULL, 'card', 'completed', 0.00, NOW() - INTERVAL 4 DAY
),

-- Objednávka 3 - Petr Dvořák
(
    NULL, 'TEST-003', 'petr.dvorak@seznam.cz', '+420 555 666 777', 'Petr Dvořák',
    'standard', '', 'Karlova 8', '120 00', 2100.00, 'Odesláno',
    'TRK003', 'Doručit v pracovní dny', 
    '[{"id":"3","name":"Design lampa","quantity":2,"price":1050,"color":"Zlatá"}]',
    0, NULL, 'paypal', 'completed', 0.00, NOW() - INTERVAL 3 DAY
),

-- Objednávka 4 - Anna Kratochvílová
(
    NULL, 'TEST-004', 'anna.kratochvilova@centrum.cz', '604 123 456', 'Anna Kratochvílová',
    'standard', '', 'Wenceslas Square 10', '110 00', 750.00, 'Doručeno',
    'TRK004', 'Děkuji za rychlé zpracování', 
    '[{"id":"4","name":"Minimalistická lampa","quantity":1,"price":750,"color":"Šedá"}]',
    0, NULL, 'bank_transfer', 'completed', 90.00, NOW() - INTERVAL 2 DAY
),

-- Objednávka 5 - Tomáš Procházka
(
    NULL, 'TEST-005', 'tomas.prochazka@email.cz', '+420 987 654 321', 'Tomáš Procházka',
    'zasilkovna', 'Zásilkovna Brno', 'Masarykova 15', '602 00', 1450.00, 'Přijato',
    'TRK005', 'Předobjednávka - čekám na dostupnost', 
    '[{"id":"5","name":"Exkluzivní lampa","quantity":1,"price":1450,"color":"Černá"}]',
    1, '2024-02-15', 'card', 'pending', 0.00, NOW() - INTERVAL 1 DAY
),

-- Objednávka 6 - Lucie Horáková
(
    NULL, 'TEST-006', 'lucie.horakova@yahoo.com', '777 111 222', 'Lucie Horáková',
    'standard', '', 'Náměstí Republiky 5', '110 00', 3200.00, 'Zpracovává se',
    'TRK006', 'Firemní objednávka - faktura', 
    '[{"id":"6","name":"Kancelářská lampa","quantity":3,"price":1067,"color":"Bílá"}]',
    0, NULL, 'bank_transfer', 'pending', 0.00, NOW() - INTERVAL 6 HOUR
),

-- Objednávka 7 - Jakub Veselý
(
    NULL, 'TEST-007', 'jakub.vesely@outlook.com', '+420 333 444 555', 'Jakub Veselý',
    'standard', '', 'Vodičkova 20', '110 00', 980.00, 'Zrušeno',
    'TRK007', 'Zrušeno zákazníkem', 
    '[{"id":"7","name":"Retro lampa","quantity":1,"price":980,"color":"Měděná"}]',
    0, NULL, 'card', 'refunded', 90.00, NOW() - INTERVAL 1 HOUR
),

-- Objednávka 8 - Veronika Černá
(
    NULL, 'TEST-008', 'veronika.cerna@email.cz', '608 999 888', 'Veronika Černá',
    'zasilkovna', 'Zásilkovna Ostrava', 'Nádražní 30', '700 30', 1650.00, 'Přijato',
    'TRK008', 'Doručit do Zásilkovny', 
    '[{"id":"8","name":"Designerská lampa","quantity":1,"price":1650,"color":"Zlatá"}]',
    0, NULL, 'paypal', 'completed', 0.00, NOW() - INTERVAL 30 MINUTE
),

-- Objednávka 9 - Martin Polák
(
    NULL, 'TEST-009', 'martin.polak@seznam.cz', '+420 666 777 888', 'Martin Polák',
    'standard', '', 'Hlavní třída 45', '301 00', 2200.00, 'Zpracovává se',
    'TRK009', 'Doručit v odpoledních hodinách', 
    '[{"id":"9","name":"Moderní lampa","quantity":2,"price":1100,"color":"Černá"}]',
    0, NULL, 'bank_transfer', 'pending', 0.00, NOW() - INTERVAL 15 MINUTE
),

-- Objednávka 10 - Petra Novotná
(
    NULL, 'TEST-010', 'petra.novotna@gmail.com', '777 333 444', 'Petra Novotná',
    'standard', '', 'Riegrova 12', '120 00', 850.00, 'Přijato',
    'TRK010', 'První objednávka - děkuji', 
    '[{"id":"10","name":"Elegantní lampa","quantity":1,"price":850,"color":"Bílá"}]',
    0, NULL, 'card', 'pending', 90.00, NOW() - INTERVAL 5 MINUTE
),

-- Objednávka 11 - David Hruška
(
    NULL, 'TEST-011', 'david.hruska@centrum.cz', '+420 111 222 333', 'David Hruška',
    'zasilkovna', 'Zásilkovna České Budějovice', 'Lannova 3', '370 01', 1950.00, 'Odesláno',
    'TRK011', 'Doručit do Zásilkovny', 
    '[{"id":"11","name":"Luxusní lampa","quantity":1,"price":1950,"color":"Stříbrná"}]',
    0, NULL, 'paypal', 'completed', 0.00, NOW() - INTERVAL 2 HOUR
),

-- Objednávka 12 - Michaela Svobodová
(
    NULL, 'TEST-012', 'michaela.svobodova@email.cz', '604 555 666', 'Michaela Svobodová',
    'standard', '', 'Palackého náměstí 7', '110 00', 1200.00, 'Doručeno',
    'TRK012', 'Výborná kvalita, doporučuji', 
    '[{"id":"12","name":"Klasická lampa","quantity":1,"price":1200,"color":"Hnědá"}]',
    0, NULL, 'bank_transfer', 'completed', 90.00, NOW() - INTERVAL 1 DAY
),

-- Objednávka 13 - Ondřej Malý
(
    NULL, 'TEST-013', 'ondrej.maly@yahoo.com', '+420 444 555 666', 'Ondřej Malý',
    'standard', '', 'Vinohradská 100', '120 00', 2800.00, 'Zpracovává se',
    'TRK013', 'Firemní objednávka', 
    '[{"id":"13","name":"Profesionální lampa","quantity":2,"price":1400,"color":"Černá"}]',
    0, NULL, 'card', 'pending', 0.00, NOW() - INTERVAL 3 HOUR
),

-- Objednávka 14 - Barbora Dvořáková
(
    NULL, 'TEST-014', 'barbora.dvorakova@outlook.com', '777 888 999', 'Barbora Dvořáková',
    'zasilkovna', 'Zásilkovna Plzeň', 'Náměstí Republiky 1', '301 00', 950.00, 'Přijato',
    'TRK014', 'Doručit do Zásilkovny', 
    '[{"id":"14","name":"Stylová lampa","quantity":1,"price":950,"color":"Zlatá"}]',
    0, NULL, 'paypal', 'completed', 0.00, NOW() - INTERVAL 45 MINUTE
),

-- Objednávka 15 - Filip Kovařík
(
    NULL, 'TEST-015', 'filip.kovarik@seznam.cz', '+420 777 888 999', 'Filip Kovařík',
    'standard', '', 'Jungmannova 25', '110 00', 1350.00, 'Zrušeno',
    'TRK015', 'Změna objednávky', 
    '[{"id":"15","name":"Design lampa","quantity":1,"price":1350,"color":"Měděná"}]',
    0, NULL, 'bank_transfer', 'refunded', 90.00, NOW() - INTERVAL 20 MINUTE
);

-- Zobrazení vytvořených objednávek
SELECT 
    order_id,
    name,
    email,
    phone_number,
    total_price,
    status,
    payment_status,
    created_at
FROM orders 
WHERE order_id LIKE 'TEST-%' 
ORDER BY created_at DESC;
