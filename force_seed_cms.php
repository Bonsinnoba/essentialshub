<?php
// force_seed_cms.php
require_once 'api/db.php';

echo "Force updating critical CMS policies...\n";

$policies = [
    [
        'slug' => 'privacy-policy',
        'title' => 'Privacy Policy',
        'content' => '
            <h2>1. Information We Collect</h2>
            <p>At our store, we collect information that allows us to provide our services. This includes:</p>
            <ul>
                <li><strong>Personal Details:</strong> Name, email address, phone number, and delivery address.</li>
                <li><strong>Transaction Data:</strong> Details about payments and the products you\'ve purchased from us.</li>
                <li><strong>Technical Data:</strong> IP address, login data, browser type, and version, time zone setting, and location.</li>
            </ul>

            <h2>2. How We Use Your Data</h2>
            <p>We use your data to process orders, manage your account, and, if you agree, to email you about other products and services we think may be of interest to you.</p>

            <h2>3. Data Security</h2>
            <p>We have put in place appropriate security measures to prevent your personal data from being accidentally lost, used, or accessed in an unauthorized way. We limit access to your personal data to those employees, agents, and contractors who have a business need to know.</p>

            <h2>4. Your Legal Rights</h2>
            <p>Under certain circumstances, you have rights under data protection laws in relation to your personal data, including the right to request access, correction, erasure, restriction, and transfer.</p>
        '
    ],
    [
        'slug' => 'terms-of-service',
        'title' => 'Terms of Service',
        'content' => '
            <h2>1. Agreement to Terms</h2>
            <p>By accessing or using our store, you agree to be bound by these Terms of Service and all applicable laws and regulations. If you do not agree with any of these terms, you are prohibited from using or accessing this site.</p>

            <h2>2. Use License</h2>
            <p>Permission is granted to temporarily download one copy of the materials (information or software) on our website for personal, non-commercial transitory viewing only.</p>

            <h2>3. Disclaimer</h2>
            <p>The materials on our website are provided on an \'as is\' basis. We make no warranties, expressed or implied, beyond those required by law. Please verify product specifications and suitability before purchase.</p>

            <h2>4. Governing Law</h2>
            <p>These terms and conditions are governed by and construed in accordance with the laws of Ghana and you irrevocably submit to the exclusive jurisdiction of the courts in that State or location.</p>
        '
    ],
    [
        'slug' => 'shipping-info',
        'title' => 'Shipping Information',
        'content' => '
            <h2>1. Delivery Options</h2>
            <p>We offer standard (3-5 business days) and express (1-2 business days) shipping options nationwide.</p>

            <h2>2. Order Tracking</h2>
            <p>Once your order is processed, a tracking link will be provided via email or SMS depending on your preferences.</p>

            <h2>3. Coverage Areas</h2>
            <p>We currently deliver to all primary regions within the country. Remote areas may incur additional delivery times.</p>

            <h2>4. Processing Time</h2>
            <p>Orders placed before 2 PM local time are processed the same business day.</p>

            <h2>5. Shipping Protection Guarantee</h2>
            <p>All orders from our store are packed with care. Fragile items are protected where appropriate. If your package arrives damaged or items are missing, contact us within 48 hours of delivery with photos — we will work with you on a fair resolution.</p>
        '
    ],
    [
        'slug' => 'return-policy',
        'title' => 'Return Policy',
        'content' => '
            <h2>1. General Returns</h2>
            <p>We stand behind every component we sell. If you receive a defective or incorrect item, we will make it right.</p>

            <h2>2. Eligible for Return</h2>
            <ul>
                <li>Dead-on-arrival (DOA) components</li>
                <li>Items that are clearly defective or damaged upon delivery</li>
                <li>Wrong item shipped</li>
            </ul>

            <h2>3. Non-Returnable Items</h2>
            <ul>
                <li>Opened component packs (resistors, etc.)</li>
                <li>Items damaged by incorrect wiring</li>
            </ul>
        '
    ],
    [
        'slug' => 'about-us',
        'title' => 'About Us',
        'content' => '
            <h2>1. Our Mission</h2>
            <p>We aim to be the premier destination for electronics enthusiasts, engineers, and hobbyists by providing high-quality components and world-class technical support.</p>

            <h2>2. Quality Standards</h2>
            <p>Every product in our catalog undergoes rigorous quality checks. We work directly with verified manufacturers to ensure that what you receive meets or exceeds industry specifications.</p>

            <h2>3. Industry Presence</h2>
            <p>Since our inception, we have powered thousands of projects across various sectors, including education, industrial automation, and consumer electronics.</p>

            <h2>4. Community Support</h2>
            <p>We believe in the power of shared knowledge. Beyond selling components, we provide resources, datasheets, and active support to help you see your projects to completion.</p>
        '
    ],
    [
        'slug' => 'faq',
        'title' => 'Frequently Asked Questions',
        'content' => '
            <h2>1. What products do you sell?</h2>
            <p>We specialize in electronic components, development boards, sensors, and STEM educational kits for all skill levels.</p>

            <h2>2. Are components genuine?</h2>
            <p>Yes. All our integrated circuits, transistors, and modules are sourced from verified supply chains to ensure authenticity and performance.</p>

            <h2>3. How do I track my order?</h2>
            <p>Once shipped, you will receive a tracking code via your registered email or SMS. You can also track live updates in your account dashboard.</p>

            <h2>4. Do you offer bulk discounts?</h2>
            <p>Yes! We offer competitive pricing for educational institutions, engineering firms, and high-volume manufacturing needs. Contact us for a quote.</p>

            <h2>5. Technical Support availability?</h2>
            <p>Our technical team is available 9am - 6pm Monday to Friday to assist with product specifications, wiring, and general troubleshooting.</p>
        '
    ]
];

foreach ($policies as $policy) {
    try {
        // Check if exists
        $stmt = $pdo->prepare("SELECT id FROM cms_pages WHERE slug = ?");
        $stmt->execute([$policy['slug']]);
        if ($stmt->fetch()) {
             $stmt = $pdo->prepare("UPDATE cms_pages SET content = ?, title = ?, is_published = 1 WHERE slug = ?");
             $stmt->execute([trim($policy['content']), $policy['title'], $policy['slug']]);
             echo "Successfully updated '{$policy['title']}'\n";
        } else {
             $stmt = $pdo->prepare("INSERT INTO cms_pages (slug, title, content, is_published) VALUES (?, ?, ?, 1)");
             $stmt->execute([$policy['slug'], $policy['title'], trim($policy['content'])]);
             echo "Successfully inserted '{$policy['title']}'\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}

echo "Force update complete.\n";
