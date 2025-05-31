<?php
session_start(); // Start the session at the very beginning

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php'); 
    exit;
}

require_once 'vendor/autoload.php'; // Composer autoload
require_once 'config.php';  // Your config file with credentials

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

$authUrl = $client->createAuthUrl();

// Retrieve and clear session messages for login page
$login_page_message_text = '';
$login_page_message_class = 'text-indigo-300'; // Default Tailwind class

if (isset($_SESSION['message'])) {
    $login_page_message_text = $_SESSION['message'];
    unset($_SESSION['message']);
    if (isset($_SESSION['message_type'])) {
        $type = $_SESSION['message_type'];
        if ($type === 'success') {
            $login_page_message_class = 'text-green-400'; 
        } elseif ($type === 'danger') {
            $login_page_message_class = 'text-red-400';   
        } elseif ($type === 'warning') {
            $login_page_message_class = 'text-yellow-400'; 
        }
        unset($_SESSION['message_type']);
    }
}

if (isset($_GET['error']) && empty($login_page_message_text)) { 
    $login_page_message_text = urldecode($_GET['error']);
    $login_page_message_class = 'text-red-400'; 
}
if (isset($_GET['logout']) && $_GET['logout'] === 'true' && empty($login_page_message_text)) {
    $login_page_message_text = 'You have been logged out successfully.';
    $login_page_message_class = 'text-green-400'; 
}
if (isset($_GET['message_text_url']) && isset($_GET['message_alert_type_url']) && empty($login_page_message_text)) {
    $login_page_message_text = urldecode($_GET['message_text_url']);
    $type_from_url = urldecode($_GET['message_alert_type_url']);
    if ($type_from_url === 'success') {
        $login_page_message_class = 'text-green-400';
    } elseif ($type_from_url === 'danger') {
        $login_page_message_class = 'text-red-400';
    } elseif ($type_from_url === 'warning') {
        $login_page_message_class = 'text-yellow-400';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Synapse AI Notes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@400;700&family=Lexend:wght@300;400;600&display=swap');

        body {
            font-family: 'Lexend', sans-serif;
            color: #E0E0E0;
            background-color: #090a0f; 
            overflow-x: hidden; 
        }

        h1, h2, h3, .font-orbitron {
            font-family: 'Orbitron', sans-serif;
        }

        .bg-deep-space { /* From your static HTML */
            background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%);
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            z-index: -2;
        }
        
        #stars-container { /* From your static HTML */
            position: fixed;
            top:0; left:0; right:0; bottom:0;
            width:100%; height:100%;
            display:block;
            z-index: -1;
        }

        .hero-section {
            min-height: 100vh;
            display: flex;
            flex-direction: column; 
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
            padding: 1rem; 
        }

        .login-capsule {
            background-color: rgba(20, 28, 42, 0.75); /* From your static HTML */
            backdrop-filter: blur(18px) saturate(180%); /* From your static HTML */
            border: 1px solid rgba(100, 116, 139, 0.35); /* From your static HTML */
            box-shadow: 0px 0px 70px rgba(100, 116, 139, 0.25); /* From your static HTML */
            animation: fadeInScaleUp 1.2s cubic-bezier(0.165, 0.84, 0.44, 1) forwards;
            border-radius: 35px; /* From your static HTML */
            width: 100%; 
            max-width: 28rem; 
            margin-bottom: 3rem; 
        }

        @keyframes fadeInScaleUp {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        
        .main-logo-container {
            animation: subtleFloat 8s ease-in-out infinite, glowEffect 3.5s ease-in-out infinite alternate;
        }

        @keyframes subtleFloat {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
            100% { transform: translateY(0px); }
        }
        @keyframes glowEffect {
            from { filter: drop-shadow(0 0 10px rgba(192, 132, 252, 0.5)); } 
            to { filter: drop-shadow(0 0 25px rgba(192, 132, 252, 0.9)); }
        }

        .google-button-styled-link { 
            background: linear-gradient(135deg, #a855f7, #7c3aed); 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 5px 20px rgba(168, 85, 247, 0.35);
            color: white; 
            font-weight: 600; 
            padding-top: 0.875rem; 
            padding-bottom: 0.875rem;
            padding-left: 1.5rem; 
            padding-right: 1.5rem;
            border-radius: 0.75rem; 
            display: inline-flex; 
            align-items: center;
            justify-content: center;
            width: 100%; 
            text-decoration: none; 
            font-size: 1rem; 
        }

        .google-button-styled-link:hover {
            background: linear-gradient(135deg, #9333ea, #6d28d9);
            transform: translateY(-4px) scale(1.06);
            box-shadow: 0 10px 30px rgba(168, 85, 247, 0.6);
        }
        .google-button-styled-link:active { /* Added from your static HTML's .google-button:active */
            transform: translateY(-2px) scale(1.03);
        }
        
        .content-section-wrapper { 
            width: 100%;
            background-color: rgba(15, 23, 42, 0.85); 
            padding-top: 3rem; 
            padding-bottom: 1rem; 
            position: relative;
            z-index: 0; 
        }
        .content-section { 
            padding-top: 5rem; 
            padding-bottom: 5rem;
        }
        .feature-card-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2rem; 
        }
        .feature-card {
            background-color: rgba(30, 41, 59, 0.65); 
            border: 1px solid rgba(100, 116, 139, 0.3); 
            border-radius: 1.5rem; 
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            width: clamp(290px, calc(33.333% - 2rem), 340px); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.25);
            animation: popIn 0.6s ease-out backwards;
            color: #d1d5db; 
            padding: 1.75rem; /* p-7 from your static HTML */
        }
        @keyframes popIn {
            0% { opacity:0; transform: scale(0.85) translateY(25px); }
            100% { opacity:1; transform: scale(1) translateY(0); }
        }
        .feature-card:hover {
            transform: translateY(-15px) scale(1.04);
            box-shadow: 0 15px 35px rgba(168, 85, 247, 0.25);
            border-color: rgba(168, 85, 247, 0.45);
        }
        .feature-icon {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.18), rgba(124, 58, 237, 0.18));
            color: #c084fc; 
            border-radius: 1.125rem; /* rounded-2xl in Tailwind is 1rem, yours was 18px */
            transition: background 0.3s ease, color 0.3s ease, transform 0.3s ease;
            width: 4rem; height: 4rem; /* w-16 h-16 */
            display: flex; align-items: center; justify-content: center;
            margin-left: auto; margin-right: auto; margin-bottom: 1.25rem; /* mb-5 */ padding: 0.875rem; /* p-3.5 */
        }
        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.35), rgba(124, 58, 237, 0.35));
            color: #e9d5ff; 
            transform: rotate(10deg) scale(1.1);
        }
        .feature-card h3 { color: #a5b4fc; font-size: 1.25rem; margin-bottom: 0.5rem; } 
        .feature-card p { font-size: 0.875rem; color: #9ca3af; line-height: 1.6; } 

        .about-section { 
            background-color: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(8px);
            padding-top: 5rem; /* py-20 */
            padding-bottom: 5rem;
        }
        .footer-section {
            background-color: rgba(9, 10, 15, 0.7); 
            border-top: 1px solid rgba(55, 65, 81, 0.5); 
            padding-top: 3rem; /* py-12 */
            padding-bottom: 3rem;
        }
        /* Bootstrap Alert Styling for PHP messages */
        .alert { 
            color: #E0E0E0; /* Ensure text is light for dark alerts */
            border-width: 1px;
            font-size: 0.9rem; /* Match messageArea styling */
            padding: 0.5rem 1rem; /* Match messageArea styling */
        }
        .alert-success { background-color: rgba(16, 185, 129, 0.2); border-color: rgba(16, 185, 129, 0.4); color: #6ee7b7;}
        .alert-danger { background-color: rgba(239, 68, 68, 0.2); border-color: rgba(239, 68, 68, 0.4); color: #f87171;}
        .alert-warning { background-color: rgba(245, 158, 11, 0.2); border-color: rgba(245, 158, 11, 0.4); color: #fcd34d;}
        .alert-dismissible .close { color: #E0E0E0; opacity: 0.7; padding: 0.5rem 1rem; }
        .alert-dismissible .close:hover { opacity: 1; }
    </style>
</head>
<body class="text-slate-200">

    <div class="bg-deep-space"></div> 
    <canvas id="stars-container"></canvas> 

    <section class="hero-section p-4">
        <div class="login-capsule p-8 md:p-12 text-center"> 
            <div class="mb-6 main-logo-container">
                <svg class="w-24 h-24 mx-auto text-purple-400" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg"> {/* Tailwind: w-24 h-24 mx-auto text-purple-400 */}
                    <path d="M32 5C16.475 5 5 16.475 5 32C5 47.525 16.475 59 32 59C47.525 59 59 47.525 59 32C59 16.475 47.525 5 32 5Z" stroke="currentColor" stroke-width="3" stroke-miterlimit="10" stroke-opacity="0.6"/>
                    <path d="M24 24V40M40 24V40M20 32H44" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" stroke-opacity="0.8"/>
                    <path d="M32 20C35.3137 20 38 22.6863 38 26C38 29.3137 35.3137 32 32 32C28.6863 32 26 29.3137 26 26C26 22.6863 28.6863 20 32 20Z" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.7"/>
                    <path d="M32 32C35.3137 32 38 34.6863 38 38C38 41.3137 35.3137 44 32 44C28.6863 44 26 41.3137 26 38C26 34.6863 28.6863 32 32 32Z" stroke="currentColor" stroke-width="2.5" stroke-opacity="0.7"/>
                    <path d="M41.1429 22.8571C43.5238 25.2381 45 28.4762 45 32C45 35.5238 43.5238 38.7619 41.1429 41.1429" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-opacity="0.5"/>
                    <path d="M22.8571 41.1429C20.4762 38.7619 19 35.5238 19 32C19 28.4762 20.4762 25.2381 22.8571 22.8571" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-opacity="0.5"/>
                </svg>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold mb-2 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-pink-400 to-indigo-400 font-orbitron">Synapse AI Notes</h1>
            <p class="text-slate-300 mb-10 text-sm md:text-base opacity-85">Intelligent Note-Taking, Reimagined.</p>
            
            <div id="messageArea" class="mt-5 text-sm h-6 mb-4"> <?php if ($login_page_message_text): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($login_page_message_type); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($login_page_message_text); ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
            </div> 

            <a href="<?php echo htmlspecialchars($authUrl); ?>" class="google-button-styled-link text-base">
                <svg class="w-5 h-5 mr-3" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"></path>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"></path>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"></path>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"></path>
                    <path fill="none" d="M0 0h48v48H0z"></path>
                </svg>
                <span>Sign In with Google</span>
            </a>
            
            <p class="mt-10 text-xs text-slate-400 opacity-60">
                &copy; <span id="currentYear"></span> Synapse Notes. All rights reserved.
            </p>
        </div>
    </section>

    <div class="content-section-wrapper"> 
        <section id="features" class="container content-section"> 
            <h2 class="text-3xl font-bold text-center mb-5 text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-indigo-400">Unlock Smart Features</h2>
            <p class="text-center text-slate-300 mb-16 max-w-2xl mx-auto opacity-85" style="font-size: 1.1rem;">
                Experience a new era of note management with Synapse AI: advanced summarization, intuitive categorization, and effortless organization.
            </p>
            <div class="feature-card-row">
                <div class="feature-card p-7 text-center" style="animation-delay: 0.1s;"> 
                    <div class="feature-icon w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5 p-3.5"> 
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-9 h-9"> 
                          <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-indigo-300">Insightful Summaries</h3> 
                    <p class="text-slate-400 text-sm leading-relaxed"> 
                        Distill lengthy texts into concise, actionable insights. Understand more, faster.
                    </p>
                </div>
                <div class="feature-card p-7 text-center" style="animation-delay: 0.25s;">
                    <div class="feature-icon w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5 p-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-9 h-9">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.759 6.759 0 010-.255c.007-.378-.137-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                          <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-indigo-300">Smart Tagging</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Notes are intelligently tagged and sorted. Effortlessly find what you're looking for.
                    </p>
                </div>
                <div class="feature-card p-7 text-center" style="animation-delay: 0.4s;">
                     <div class="feature-icon w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-5 p-3.5">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-9 h-9">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2 text-indigo-300">Fluid Workflow</h3>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        An intuitive and adaptive interface. Secure, accessible, and always ready.
                    </p>
                </div>
            </div>
        </section>

        <section id="about" class="container content-section about-section py-20"> 
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h2 class="text-3xl font-bold mb-8 text-transparent bg-clip-text bg-gradient-to-r from-purple-300 to-indigo-300">The Synapse Vision</h2>
                    <p class="text-slate-300 mb-6 leading-relaxed opacity-90 text-lg">
                        Synapse AI Notes is a forward-thinking student initiative, driven by the desire to weave practical AI into essential academic tools. We're building a genuinely helpful assistant to manage study materials with enhanced efficiency and deeper understanding.
                    </p>
                    <p class="text-slate-400 text-base opacity-80"> 
                        This platform embodies modern application principles: a resilient backend, a dynamic frontend, and the transformative potential of AI for an intelligent user journey. It’s about evolving how we learn.
                    </p>
                </div>
            </div>
        </section>
    </div>

    <footer class="footer-section content-section py-12">
        <div class="container text-center"> 
            <p class="text-slate-400 text-sm"> 
                <span class="font-orbitron text-base">Synapse</span> AI Notes &copy; <span id="footerYear"></span>.
                A Student-Led Innovation.
            </p>
             <p class="text-xs text-slate-500 mt-2">Crafted with passion by aspiring developers.</p> 
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        document.getElementById('currentYear').textContent = new Date().getFullYear();
        document.getElementById('footerYear').textContent = new Date().getFullYear();

        // Auto-dismiss alerts from PHP after 3 seconds
        window.setTimeout(function() {
            const alertMessageArea = document.getElementById('messageArea');
            if (alertMessageArea) {
                const alertElement = alertMessageArea.querySelector('.alert'); // Assuming Bootstrap alert classes
                if (alertElement && window.jQuery && $.fn.alert) { 
                    $(alertElement).alert('close'); 
                } else if (alertElement) { 
                    alertElement.style.display = 'none';
                }
            }
        }, 3000); 
        
        const starsContainer = document.getElementById('stars-container'); // Matches your static HTML ID
        if (starsContainer && starsContainer.getContext) { 
            const ctx = starsContainer.getContext('2d');
            let stars = [];
            let numStars = window.innerWidth < 768 ? 100 : 200; 

            function setCanvasSize() {
                starsContainer.width = window.innerWidth;
                starsContainer.height = Math.max(document.body.scrollHeight, window.innerHeight); 
            }

            function createStars() {
                stars = [];
                for (let i = 0; i < numStars; i++) {
                    stars.push({
                        x: Math.random() * starsContainer.width,
                        y: Math.random() * starsContainer.height,
                        radius: Math.random() * 1.3 + 0.6, 
                        alpha: Math.random() * 0.6 + 0.4,  
                        vx: (Math.random() - 0.5) * 0.15, 
                        vy: (Math.random() - 0.5) * 0.15  
                    });
                }
            }

            function drawStars() {
                if (!ctx) return; 
                ctx.clearRect(0, 0, starsContainer.width, starsContainer.height);
                stars.forEach(star => {
                    ctx.beginPath();
                    ctx.arc(star.x, star.y, star.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(224, 224, 240, ${star.alpha})`; 
                    ctx.fill();
                });
            }

            function updateStars() {
                stars.forEach(star => {
                    star.x += star.vx;
                    star.y += star.vy;

                    if (star.x < 0 || star.x > starsContainer.width) star.vx *= -0.95; 
                    if (star.y < 0 || star.y > starsContainer.height) star.vy *= -0.95; 
                    
                    star.alpha += (Math.random() -0.5) * 0.06; 
                    if(star.alpha < 0.2) star.alpha = 0.2; 
                    if(star.alpha > 0.9) star.alpha = 0.9; 
                });
            }
            
            let animationFrameId;
            function animateStars() {
                drawStars();
                updateStars();
                animationFrameId = requestAnimationFrame(animateStars);
            }
            
            function initStars() {
                if (animationFrameId) {
                    cancelAnimationFrame(animationFrameId);
                }
                setCanvasSize();
                createStars();
                animateStars();
            }

            window.addEventListener('resize', initStars);
            
            if (document.readyState === 'complete') {
                initStars();
            } else {
                window.addEventListener('load', initStars);
            }
        } else if (starsContainer && !starsContainer.getContext) {
            console.warn("Canvas element 'stars-container' found, but getContext is not available. Stars will not be animated.");
        }
    </script>

</body>
</html>
