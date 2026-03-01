<?php
require_once 'includes/db.php';

$appliances = [
    // Kitchen (Tamil Nadu Specific & Common Indian)
    ['Wet Grinder (Tilting)', 'Kitchen', 150],
    ['Wet Grinder (Standard)', 'Kitchen', 95],
    ['Mixer Grinder (Mixie 500W)', 'Kitchen', 500],
    ['Mixer Grinder (Mixie 750W)', 'Kitchen', 750],
    ['Induction Stove (Standard)', 'Kitchen', 2000],
    ['Electric Kettle (1.5L)', 'Kitchen', 1500],
    ['Refrigerator (Single Door)', 'Kitchen', 100],
    ['Refrigerator (Double Door)', 'Kitchen', 250],
    ['Microwave Oven (Solo)', 'Kitchen', 800],
    ['OTG (Oven Toaster Grill)', 'Kitchen', 1200],
    ['Rice Cooker (Electric)', 'Kitchen', 700],
    ['Coffee Filter Machine', 'Kitchen', 600],
    ['Water Purifier (RO)', 'Kitchen', 40],
    ['Exhaust Fan (Kitchen)', 'Kitchen', 45],

    // HVAC & Comfort (TN Climate focus)
    ['Ceiling Fan (Standard)', 'HVAC', 75],
    ['Ceiling Fan (BEE Star Rated)', 'HVAC', 50],
    ['Table Fan', 'HVAC', 55],
    ['Pedestal Fan', 'HVAC', 70],
    ['Air Conditioner (1 Ton Inverter)', 'HVAC', 1000],
    ['Air Conditioner (1.5 Ton Inverter)', 'HVAC', 1500],
    ['Air Cooler (Desert)', 'HVAC', 200],
    ['Air Cooler (Personal)', 'HVAC', 150],
    ['Water Heater (Geyser 15L)', 'HVAC', 2000],
    ['Water Heater (Geyser 25L)', 'HVAC', 3000],
    ['Instant Water Heater', 'HVAC', 3000],
    ['Solar Water Heater (Pump)', 'HVAC', 100],

    // Lighting (TN Households)
    ['LED Bulb (9W)', 'Lighting', 9],
    ['LED Tube Light (20W)', 'Lighting', 20],
    ['LED Panel Light', 'Lighting', 15],
    ['CFL Bulb (18W)', 'Lighting', 18],
    ['Night Lamp (LED)', 'Lighting', 0.5],
    ['Decorative Serial Lights', 'Lighting', 40],
    ['Outdoor Gate Light', 'Lighting', 10],

    // Entertainment & Electronics
    ['TV (32" LED)', 'Entertainment', 45],
    ['TV (43" Smart LED)', 'Entertainment', 75],
    ['TV (55" 4K LED)', 'Entertainment', 110],
    ['Set Top Box (HD)', 'Entertainment', 15],
    ['Home Theater (5.1)', 'Entertainment', 150],
    ['Soundbar (2.1)', 'Entertainment', 60],
    ['Desktop PC', 'Office', 200],
    ['Laptop (Charging)', 'Office', 65],
    ['Wi-Fi Router', 'Electronics', 10],
    ['Inverter (Idle Charge)', 'Electronics', 20],
    ['CCTV Camera System', 'Security', 40],

    // Laundry & Cleaning
    ['Washing Machine (Semi-Auto)', 'Laundry', 350],
    ['Washing Machine (Fully Auto Top Load)', 'Laundry', 500],
    ['Washing Machine (Fully Auto Front Load)', 'Laundry', 2000],
    ['Electric Steam Iron', 'Laundry', 1200],
    ['Dry Iron', 'Laundry', 1000],
    ['Vacuum Cleaner', 'Cleaning', 1200],

    // Professional & Tools (Farm/Small Business)
    ['Agricultural Pump (3HP)', 'Outdoor', 2238],
    ['Agricultural Pump (5HP)', 'Outdoor', 3730],
    ['Borewell Submersible Pump (1HP)', 'Outdoor', 746],
    ['Mono Block Pump (0.5HP)', 'Outdoor', 373],
    ['Drilling Machine (Handheld)', 'Tools', 500],
    ['Electric Sewing Machine', 'Miscellaneous', 100],
];

try {
    $pdo->beginTransaction();
    $pdo->exec("DELETE FROM appliances"); // Clear old list
    $stmt = $pdo->prepare("INSERT INTO appliances (name, category, estimated_wattage) VALUES (?, ?, ?)");
    foreach ($appliances as $app) {
        $stmt->execute($app);
    }
    $pdo->commit();
    echo "Tamil Nadu specific appliances seeded successfully!";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
?>
