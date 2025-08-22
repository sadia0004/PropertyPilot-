<?php
// --- DB Connection ---
$conn = new mysqli("localhost", "root", "", "property");
if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// --- Handle Search and Filter ---
$location = $_GET['location'] ?? '';
$price_range = $_GET['price_range'] ?? '';
$sort = $_GET['sort'] ?? 'date_desc';

// --- Fetch Vacancy Posts ---
$vacancy_posts = [];
$base_query = "
    SELECT 
        vp.post_id, vp.title, vp.description, vp.location,
        p.apartment_no, p.apartment_rent, p.apartment_type, p.apartment_size, p.floor_no,
        u.fullName AS landlord_name,
        (SELECT GROUP_CONCAT(image_path SEPARATOR ',') FROM post_images WHERE post_id = vp.post_id) as images
    FROM vacancy_posts vp
    JOIN properties p ON vp.property_id = p.property_id
    JOIN users u ON vp.landlord_id = u.id
    WHERE p.apartment_status = 'Vacant'
";
$params = []; $types = "";
if (!empty($location)) {
    $base_query .= " AND vp.location LIKE ?";
    $like_location = "%" . $location . "%";
    $params[] = $like_location; $types .= "s";
}
if (!empty($price_range)) {
    $price_parts = explode('-', $price_range);
    $min_price = $price_parts[0]; $max_price = $price_parts ?? null;
    if (is_numeric($min_price)) { $base_query .= " AND p.apartment_rent >= ?"; $params[] = $min_price; $types .= "d"; }
    if (is_numeric($max_price)) { $base_query .= " AND p.apartment_rent <= ?"; $params[] = $max_price; $types .= "d"; }
}
if ($sort === 'price_asc') { $base_query .= " ORDER BY p.apartment_rent ASC"; } 
elseif ($sort === 'price_desc') { $base_query .= " ORDER BY p.apartment_rent DESC"; } 
else { $base_query .= " ORDER BY vp.created_at DESC"; }

$stmt = $conn->prepare($base_query);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $row['images'] = !empty($row['images']) ? explode(',', $row['images']) : [];
        $vacancy_posts[] = $row;
    }
}
$stmt->close();

// --- Fetch Platform Stats ---
$totalLandlords = $conn->query("SELECT COUNT(*) as count FROM users WHERE userRole = 'landlord'")->fetch_assoc()['count'];
$totalTenants = $conn->query("SELECT COUNT(*) as count FROM users WHERE userRole = 'tenant'")->fetch_assoc()['count'];
$totalProperties = $conn->query("SELECT COUNT(*) as count FROM properties")->fetch_assoc()['count'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>PropertyPilot - Modern Property Management Made Simple</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        html { scroll-behavior: smooth; }
        
        .hero-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }
        
        .hero-gradient::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('https://images.unsplash.com/photo-1560518883-ce09059eeffa?q=80&w=2070&auto=format&fit=crop') center/cover;
            opacity: 0.15;
            z-index: 1;
        }
        
        .hero-content { position: relative; z-index: 2; }
        
        .floating-element {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
        
        .gradient-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            transform: translateY(0);
            transition: transform 0.3s ease;
        }
        
        .gradient-card:hover { transform: translateY(-10px); }
        
        .feature-card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .slideshow-container {
            height: 280px;
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .slideshow-track {
            display: flex;
            height: 100%;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .slide-image {
            min-width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .slide-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.9);
            color: #4f46e5;
            border: none;
            padding: 12px;
            border-radius: 50%;
            cursor: pointer;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 14px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .slideshow-container:hover .slide-btn { opacity: 1; }
        .slide-btn:hover { background: rgba(255, 255, 255, 1); transform: translateY(-50%) scale(1.1); }
        .slide-btn.prev { left: 15px; }
        .slide-btn.next { right: 15px; }
        
        .property-card {
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
        }
        
        .property-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }
        
        .price-badge {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: white;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 25px;
            display: inline-block;
        }
        
        .navbar-blur {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.95);
        }
        
        .cta-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stats-counter {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 4rem 0;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <!-- Enhanced Header -->
<header class="navbar-blur shadow-lg sticky top-0 z-50 border-b border-gray-200">
    <nav class="container mx-auto px-6 py-4 flex justify-between items-center">
        <a href="index.php" class="text-2xl font-bold text-gray-800 flex items-center hover:scale-105 transition-transform">
            <img src="image/logo.png" alt="PropertyPilot Logo" class="h-18 w-16 mr-3 rounded-xl object-contain ">
            <span class="bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">PropertyPilot</span>
        </a>
        <div class="hidden md:flex items-center space-x-8">
            <a href="#home" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors relative group">
                Home
                <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 transition-all group-hover:w-full"></span>
            </a>
            <a href="#apartments" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors relative group">
                Properties
                <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 transition-all group-hover:w-full"></span>
            </a>
            <a href="#features" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors relative group">
                Features
                <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 transition-all group-hover:w-full"></span>
            </a>
            <a href="#reviews" class="text-gray-600 hover:text-indigo-600 font-medium transition-colors relative group">
                Reviews
                <span class="absolute -bottom-1 left-0 w-0 h-0.5 bg-indigo-600 transition-all group-hover:w-full"></span>
            </a>
        </div>
        <div class="flex items-center space-x-4">
            <a href="login.php" class="px-6 py-2.5 text-indigo-600 border-2 border-indigo-600 rounded-full hover:bg-indigo-600 hover:text-white transition-all font-medium">
                Login
            </a>
            <a href="register_user.php" class="px-6 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-full hover:shadow-lg transform hover:scale-105 transition-all font-medium">
                Get Started
            </a>
        </div>
    </nav>
</header>


    <!-- Enhanced Hero Section -->
    <section id="home" class="hero-gradient text-white py-32 relative">
        <div class="hero-content container mx-auto px-6 text-center">
            <div class="floating-element">
                <h1 class="text-6xl md:text-7xl font-extrabold mb-6 leading-tight">
                    Your Dream Home
                    <span class="block bg-gradient-to-r from-yellow-300 to-pink-300 bg-clip-text text-transparent">
                        Awaits You
                    </span>
                </h1>
            </div>
            <p class="text-xl text-gray-200 mb-12 max-w-3xl mx-auto leading-relaxed">
                Connect directly with verified landlords, discover premium properties, and manage your entire rental journey through our intelligent platform.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                <a href="#apartments" class="px-8 py-4 bg-white text-indigo-600 rounded-full font-semibold text-lg hover:shadow-2xl transform hover:scale-105 transition-all">
                    <i class="fas fa-search mr-2"></i>
                    Explore Properties
                </a>
                <a href="register_user.php" class="px-8 py-4 border-2 border-white text-white rounded-full font-semibold text-lg hover:bg-white hover:text-indigo-600 transition-all">
                    <i class="fas fa-user-plus mr-2"></i>
                    Join Today
                </a>
            </div>
        </div>
        
        <!-- Floating Elements -->
        <div class="absolute top-20 left-10 floating-element" style="animation-delay: -2s;">
            <div class="w-20 h-20 bg-white bg-opacity-10 rounded-full blur-sm"></div>
        </div>
        <div class="absolute bottom-20 right-10 floating-element" style="animation-delay: -4s;">
            <div class="w-32 h-32 bg-white bg-opacity-10 rounded-full blur-sm"></div>
        </div>
    </section>

    <!-- Enhanced Stats Section -->
    <section class="bg-white py-16 -mt-20 relative z-10 mx-auto max-w-6xl rounded-2xl shadow-2xl border border-gray-100">
        <div class="container mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center">
                <div class="group">
                    <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center transform group-hover:scale-110 transition-transform">
                        <i class="fas fa-user-tie text-3xl text-white"></i>
                    </div>
                    <p class="stats-counter"><?php echo number_format($totalLandlords); ?>+</p>
                    <p class="text-gray-600 font-medium">Verified Landlords</p>
                    <p class="text-sm text-gray-500 mt-1">Trusted property owners</p>
                </div>
                <div class="group">
                    <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center transform group-hover:scale-110 transition-transform">
                        <i class="fas fa-users text-3xl text-white"></i>
                    </div>
                    <p class="stats-counter"><?php echo number_format($totalTenants); ?>+</p>
                    <p class="text-gray-600 font-medium">Happy Tenants</p>
                    <p class="text-sm text-gray-500 mt-1">Satisfied residents</p>
                </div>
                <div class="group">
                    <div class="w-20 h-20 mx-auto mb-4 bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center transform group-hover:scale-110 transition-transform">
                        <i class="fas fa-building text-3xl text-white"></i>
                    </div>
                    <p class="stats-counter"><?php echo number_format($totalProperties); ?>+</p>
                    <p class="text-gray-600 font-medium">Premium Properties</p>
                    <p class="text-sm text-gray-500 mt-1">Quality listings</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Properties Section -->
    <section id="apartments" class="py-24 bg-gradient-to-br from-gray-50 to-blue-50">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold mb-6 bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                    Premium Properties Available
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Discover hand-picked properties from verified landlords in prime locations
                </p>
            </div>
            
            <!-- Enhanced Search Form -->
            <div class="bg-white p-8 rounded-2xl shadow-xl mb-16 border border-gray-100">
                <form action="index.php#apartments" method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-6 items-end">
                    <div class="md:col-span-2">
                        <label for="location" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-map-marker-alt mr-2 text-indigo-500"></i>Location
                        </label>
                        <input type="text" name="location" id="location" 
                               class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg py-3 px-4" 
                               placeholder="e.g., Dhanmondi, Gulshan" 
                               value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    <div class="md:col-span-2">
                        <label for="price_range" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-dollar-sign mr-2 text-indigo-500"></i>Budget Range (৳)
                        </label>
                        <select name="price_range" id="price_range" 
                                class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-lg py-3 px-4">
                            <option value="">Any Budget</option>
                            <option value="0-10000" <?php if ($price_range == '0-10000') echo 'selected'; ?>>Under ৳10,000</option>
                            <option value="10000-20000" <?php if ($price_range == '10000-20000') echo 'selected'; ?>>৳10,000 - ৳20,000</option>
                            <option value="20000-30000" <?php if ($price_range == '20000-30000') echo 'selected'; ?>>৳20,000 - ৳30,000</option>
                            <option value="30000-50000" <?php if ($price_range == '30000-50000') echo 'selected'; ?>>৳30,000 - ৳50,000</option>
                            <option value="50000" <?php if ($price_range == '50000') echo 'selected'; ?>>Over ৳50,000</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3.5 px-6 rounded-xl font-semibold text-lg hover:shadow-lg transform hover:scale-105 transition-all">
                            <i class="fas fa-search mr-2"></i>Find Properties
                        </button>
                    </div>
                </form>
            </div>

            <?php if (empty($vacancy_posts)): ?>
                <div class="text-center py-16">
                    <div class="w-32 h-32 mx-auto mb-8 bg-gray-100 rounded-full flex items-center justify-center">
                        <i class="fas fa-home text-5xl text-gray-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-600 mb-4">No Properties Found</h3>
                    <p class="text-gray-500 text-lg mb-8">Try adjusting your search criteria or check back later for new listings.</p>
                    <a href="index.php" class="px-6 py-3 bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors">
                        Clear Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($vacancy_posts as $post): ?>
                    <div class="property-card rounded-2xl shadow-xl overflow-hidden border border-gray-100">
                        <div class="slideshow-container">
                            <div class="slideshow-track" id="track-<?php echo $post['post_id']; ?>">
                                <?php if (!empty($post['images'])): ?>
                                    <?php foreach ($post['images'] as $image): ?>
                                        <img src="<?php echo htmlspecialchars($image); ?>" alt="Property Image" class="slide-image">
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="slide-image bg-gradient-to-br from-gray-200 to-gray-300 flex items-center justify-center">
                                        <div class="text-center">
                                            <i class="fas fa-image text-6xl text-gray-400 mb-4"></i>
                                            <p class="text-gray-500 font-medium">No Image Available</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (count($post['images']) > 1): ?>
                                <button class="slide-btn prev" onclick="moveSlide(<?php echo $post['post_id']; ?>, -1)">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="slide-btn next" onclick="moveSlide(<?php echo $post['post_id']; ?>, 1)">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                            <?php endif; ?>
                            <div class="absolute top-4 right-4 bg-white bg-opacity-90 backdrop-blur-sm px-3 py-1 rounded-full">
                                <i class="fas fa-heart text-gray-400 hover:text-red-500 cursor-pointer transition-colors"></i>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <p class="text-sm text-indigo-600 font-semibold mb-1 flex items-center">
                                        <i class="fas fa-map-marker-alt mr-1"></i>
                                        <?php echo htmlspecialchars($post['location']); ?>
                                    </p>
                                    <h3 class="text-xl font-bold text-gray-800 mb-2"><?php echo htmlspecialchars($post['title']); ?></h3>
                                </div>
                                <div class="price-badge text-right">
                                    ৳<?php echo number_format($post['apartment_rent']); ?>
                                    <div class="text-xs opacity-80">/month</div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div class="flex items-center text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <i class="fas fa-building fa-fw mr-3 text-indigo-500"></i>
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($post['apartment_type']); ?></div>
                                        <div class="text-xs text-gray-500">Property Type</div>
                                    </div>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <i class="fas fa-ruler-combined fa-fw mr-3 text-indigo-500"></i>
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($post['apartment_size']); ?> sq ft</div>
                                        <div class="text-xs text-gray-500">Floor Area</div>
                                    </div>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <i class="fas fa-layer-group fa-fw mr-3 text-indigo-500"></i>
                                    <div>
                                        <div class="font-medium">Floor <?php echo htmlspecialchars($post['floor_no']); ?></div>
                                        <div class="text-xs text-gray-500">Floor Level</div>
                                    </div>
                                </div>
                                <div class="flex items-center text-sm text-gray-600 bg-gray-50 p-3 rounded-lg">
                                    <i class="fas fa-user-tie fa-fw mr-3 text-indigo-500"></i>
                                    <div>
                                        <div class="font-medium"><?php echo htmlspecialchars($post['landlord_name']); ?></div>
                                        <div class="text-xs text-gray-500">Owner</div>
                                    </div>
                                </div>
                            </div>

                            <p class="text-gray-600 text-sm mb-6 leading-relaxed">
                                <?php echo nl2br(htmlspecialchars(substr($post['description'], 0, 150) . (strlen($post['description']) > 150 ? '...' : ''))); ?>
                            </p>
                            
                            <div class="flex gap-3">
                                <a href="register_user.php" class="flex-1 text-center py-3 px-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-xl font-semibold hover:shadow-lg transform hover:scale-105 transition-all">
                                    <i class="fas fa-paper-plane mr-2"></i>Apply Now
                                </a>
                                <button class="px-4 py-3 border-2 border-indigo-600 text-indigo-600 rounded-xl hover:bg-indigo-600 hover:text-white transition-all">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="section-divider"></div>

    <!-- Enhanced Features Section -->
    <section id="features" class="py-24 relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-purple-50"></div>
        <div class="container mx-auto px-6 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold mb-6 bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">
                    Everything You Need
                </h2>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Powerful features designed to make property management effortless for everyone
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="feature-card p-8 rounded-2xl border border-gray-200 text-center group">
                    <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-credit-card text-3xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-gray-800">Secure Payments</h3>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Process rent payments securely with multiple payment options. Automatic receipts and payment tracking included.
                    </p>
                    <div class="flex justify-center space-x-2">
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs rounded-full">SSL Secured</span>
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Auto Receipt</span>
                    </div>
                </div>
                
                <div class="feature-card p-8 rounded-2xl border border-gray-200 text-center group">
                    <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-tools text-3xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-gray-800">Smart Maintenance</h3>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Submit maintenance requests with photos, track progress in real-time, and get instant updates on resolution status.
                    </p>
                    <div class="flex justify-center space-x-2">
                        <span class="px-3 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">Photo Upload</span>
                        <span class="px-3 py-1 bg-purple-100 text-purple-800 text-xs rounded-full">Real-time</span>
                    </div>
                </div>
                
                <div class="feature-card p-8 rounded-2xl border border-gray-200 text-center group">
                    <div class="w-20 h-20 mx-auto mb-6 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center transform group-hover:scale-110 transition-all">
                        <i class="fas fa-bell text-3xl text-white"></i>
                    </div>
                    <h3 class="text-2xl font-bold mb-4 text-gray-800">Smart Notifications</h3>
                    <p class="text-gray-600 leading-relaxed mb-6">
                        Never miss important updates with intelligent notifications for payments, maintenance, and property announcements.
                    </p>
                    <div class="flex justify-center space-x-2">
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Push Alerts</span>
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">Email & SMS</span>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-16">
                <a href="register_user.php" class="px-8 py-4 bg-gradient-to-r from-indigo-600 to-purple-600 text-white rounded-full font-semibold text-lg hover:shadow-lg transform hover:scale-105 transition-all">
                    <i class="fas fa-rocket mr-2"></i>
                    Start Your Journey
                </a>
            </div>
        </div>
    </section>

    <!-- Enhanced Reviews Section -->
    <section id="reviews" class="py-24 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white relative overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/10 to-purple-500/10"></div>
        </div>
        
        <div class="container mx-auto px-6 relative z-10">
            <div class="text-center mb-16">
                <h2 class="text-5xl font-bold mb-6">What Our Community Says</h2>
                <p class="text-xl text-gray-300 max-w-2xl mx-auto">
                    Real stories from landlords and tenants who trust PropertyPilot
                </p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-white/10 backdrop-blur-lg p-8 rounded-2xl border border-white/20 hover:bg-white/15 transition-all">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-pink-400 to-red-500 rounded-full flex items-center justify-center text-2xl font-bold mr-4">
                            AR
                        </div>
                        <div>
                            <h4 class="font-bold text-lg">Sabiha Begum</h4>
                            <p class="text-gray-300">Property Owner</p>
                        </div>
                    </div>
                    <div class="flex text-yellow-400 mb-4 text-lg">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-200 leading-relaxed mb-4">
                        "PropertyPilot transformed how I manage my 12 rental properties. The automated payment system and maintenance tracking have saved me countless hours."
                    </p>
                    <div class="flex items-center text-sm text-gray-300">
                        <i class="fas fa-calendar mr-2"></i>
                        <span>Using for 2+ years</span>
                    </div>
                </div>
                
                <div class="bg-white/10 backdrop-blur-lg p-8 rounded-2xl border border-white/20 hover:bg-white/15 transition-all">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-indigo-500 rounded-full flex items-center justify-center text-2xl font-bold mr-4">
                            FA
                        </div>
                        <div>
                            <h4 class="font-bold text-lg">Rafique Ahmed</h4>
                            <p class="text-gray-300">Tenant</p>
                        </div>
                    </div>
                    <div class="flex text-yellow-400 mb-4 text-lg">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                    </div>
                    <p class="text-gray-200 leading-relaxed mb-4">
                        "Finding my apartment was so easy, and now paying rent is just a click away. The maintenance request system works like magic - issues get resolved fast!"
                    </p>
                    <div class="flex items-center text-sm text-gray-300">
                        <i class="fas fa-home mr-2"></i>
                        <span>Happy resident in Gulshan</span>
                    </div>
                </div>
                
                <div class="bg-white/10 backdrop-blur-lg p-8 rounded-2xl border border-white/20 hover:bg-white/15 transition-all">
                    <div class="flex items-center mb-6">
                        <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center text-2xl font-bold mr-4">
                            SI
                        </div>
                        <div>
                            <h4 class="font-bold text-lg">Sheikh Jamil</h4>
                            <p class="text-gray-300">New Resident</p>
                        </div>
                    </div>
                    <div class="flex text-yellow-400 mb-4 text-lg">
                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                    </div>
                    <p class="text-gray-200 leading-relaxed mb-4">
                        "The search filters are incredibly detailed, and I found my perfect apartment in Dhanmondi within just 3 days. The landlord communication is seamless!"
                    </p>
                    <div class="flex items-center text-sm text-gray-300">
                        <i class="fas fa-star mr-2"></i>
                        <span>5-star experience</span>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-16">
                <div class="inline-flex items-center space-x-8">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-yellow-400">4.9/5</div>
                        <div class="text-sm text-gray-300">Average Rating</div>
                    </div>
                    <div class="w-px h-16 bg-gray-600"></div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-400">98%</div>
                        <div class="text-sm text-gray-300">Satisfaction Rate</div>
                    </div>
                    <div class="w-px h-16 bg-gray-600"></div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-400">24/7</div>
                        <div class="text-sm text-gray-300">Support Available</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Call to Action Section -->
    <section class="cta-gradient py-20 text-white">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-4xl md:text-5xl font-bold mb-6">Ready to Transform Your Property Experience?</h2>
            <p class="text-xl mb-10 max-w-2xl mx-auto opacity-90">
                Join thousands of satisfied users who have simplified their property management with PropertyPilot.
            </p>
            <div class="flex flex-col sm:flex-row gap-6 justify-center">
                <a href="register_user.php" class="px-8 py-4 bg-white text-indigo-600 rounded-full font-bold text-lg hover:shadow-2xl transform hover:scale-105 transition-all">
                    <i class="fas fa-user-plus mr-2"></i>
                    Create Free Account
                </a>
                <a href="login.php" class="px-8 py-4 border-2 border-white text-white rounded-full font-bold text-lg hover:bg-white hover:text-indigo-600 transition-all">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Login to Dashboard
                </a>
            </div>
        </div>
    </section>
    
 <!-- Enhanced Footer -->
<footer class="bg-gray-900 text-gray-300 py-16 border-t border-gray-800">
    <div class="container mx-auto px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-12">
            <div>
                <div class="flex items-center mb-6">
                    <img src="image/logo.jpg" alt="PropertyPilot Logo" class="h-10 w-10 mr-3 rounded-lg object-contain">
                    <span class="text-2xl font-bold text-white">PropertyPilot</span>
                </div>
                <p class="text-gray-400 leading-relaxed">
                    Making property management simple, secure, and efficient for everyone in Bangladesh.
                </p>
            </div>
            <!-- Rest of the footer content remains the same -->
            <div>
                <h4 class="font-bold text-white mb-4">Quick Links</h4>
                <ul class="space-y-2">
                    <li><a href="#home" class="hover:text-indigo-400 transition-colors">Home</a></li>
                    <li><a href="#apartments" class="hover:text-indigo-400 transition-colors">Properties</a></li>
                    <li><a href="#features" class="hover:text-indigo-400 transition-colors">Features</a></li>
                    <li><a href="#reviews" class="hover:text-indigo-400 transition-colors">Reviews</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white mb-4">Support</h4>
                <ul class="space-y-2">
                    <li><a href="#" class="hover:text-indigo-400 transition-colors">Help Center</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition-colors">Contact Us</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition-colors">Privacy Policy</a></li>
                    <li><a href="#" class="hover:text-indigo-400 transition-colors">Terms of Service</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white mb-4">Connect With Us</h4>
                <div class="flex space-x-4 mb-4">
                    <a href="#" class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-facebook-f text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gradient-to-br from-pink-500 to-rose-600 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-instagram text-white"></i>
                    </a>
                    <a href="#" class="w-10 h-10 bg-gradient-to-br from-blue-400 to-blue-500 rounded-lg flex items-center justify-center hover:scale-110 transition-transform">
                        <i class="fab fa-twitter text-white"></i>
                    </a>
                </div>
                <p class="text-sm text-gray-400">
                    <i class="fas fa-phone mr-2"></i>+880 1XXX-XXXXXX
                </p>
            </div>
        </div>
        <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-gray-400">&copy; <?php echo date("Y"); ?> PropertyPilot. All rights reserved.</p>
            <p class="text-gray-400 mt-4 md:mt-0">Made with <i class="fas fa-heart text-red-500"></i> in Bangladesh</p>
        </div>
    </div>
</footer>


    <script>
        // Enhanced slideshow functionality
        let slideIndices = {};
        
        function moveSlide(postId, direction) {
            const track = document.getElementById(`track-${postId}`);
            const slides = track.getElementsByClassName('slide-image');
            const totalSlides = slides.length;

            if (totalSlides <= 1) return;

            if (!slideIndices[postId]) {
                slideIndices[postId] = 0;
            }

            slideIndices[postId] += direction;

            if (slideIndices[postId] >= totalSlides) {
                slideIndices[postId] = 0;
            }
            if (slideIndices[postId] < 0) {
                slideIndices[postId] = totalSlides - 1;
            }

            track.style.transform = `translateX(-${slideIndices[postId] * 100}%)`;
        }

        // Auto-advance slideshow
        setInterval(() => {
            document.querySelectorAll('.slideshow-container').forEach(container => {
                const postId = container.querySelector('.slideshow-track').id.split('-')[1];
                const totalSlides = container.querySelectorAll('.slide-image').length;
                if (totalSlides > 1) {
                    moveSlide(parseInt(postId), 1);
                }
            });
        }, 5000);

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add scroll effect to navbar
        window.addEventListener('scroll', () => {
            const navbar = document.querySelector('header');
            if (window.scrollY > 100) {
                navbar.classList.add('shadow-2xl');
            } else {
                navbar.classList.remove('shadow-2xl');
            }
        });
    </script>
</body>
</html>
