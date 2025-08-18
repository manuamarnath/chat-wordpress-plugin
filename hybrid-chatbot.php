<?php
/*
Plugin Name: Hybrid Chatbot
Description: A powerful, high-performance AI + human hybrid chatbot solution for WordPress. 
Seamlessly integrates into your site with advanced customization options. 
Includes smart lead collection, lead generation, and automated support features with optional 
human handover. Built for speed, reliability, and scalability, Hybrid Chatbot ensures 
next-level customer engagement and real-time conversations for maximum business growth.
Version: 1.3
Author: Echo5Digital
Author URI: https://www.echo5digital.com/
*/

// 1. Add top-level menu for Hybrid Chat settings
add_action('admin_menu', function() {
    add_menu_page(
        'Hybrid Chat Settings',
        'Hybrid Chat',
        'manage_options',
        'hybrid-chat',
        'hybrid_chat_settings_page',
        'dashicons-format-chat',
        60
    );
});

function hybrid_chat_settings_page() {
    $site_id = get_option('hybrid_chat_site_id');
    $jwt = get_option('hybrid_chat_jwt');
    $login_error = '';
    // Handle login POST
    if (isset($_POST['hybrid_chat_login'])) {
        $email = sanitize_email($_POST['hybrid_chat_email']);
        $password = sanitize_text_field($_POST['hybrid_chat_password']);
        $api_url = 'https://echo-ai-chat-server.onrender.com/auth';
        $response = wp_remote_post($api_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body' => json_encode([ 'userId' => $email, 'password' => $password ])
        ]);
        if (is_wp_error($response)) {
            $login_error = 'Could not connect to backend.';
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (!empty($body['token']) && !empty($body['siteId'])) {
                update_option('hybrid_chat_jwt', $body['token']);
                update_option('hybrid_chat_site_id', $body['siteId']);
                $site_id = $body['siteId'];
                $jwt = $body['token'];
                // Fetch and pre-fill bot settings after login
                $config_url = 'https://echo-ai-chat-server.onrender.com/config?siteId=' . urlencode($site_id);
                $config_response = wp_remote_get($config_url, [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $jwt
                    ]
                ]);
                if (!is_wp_error($config_response)) {
                    $config = json_decode(wp_remote_retrieve_body($config_response), true);
                    if (!empty($config['botName'])) update_option('hybrid_chat_bot_name', $config['botName']);
                    if (!empty($config['welcomeMessage'])) update_option('hybrid_chat_welcome', $config['welcomeMessage']);
                    if (!empty($config['style'])) update_option('hybrid_chat_style', $config['style']);
                }
            } else {
                $login_error = !empty($body['error']) ? $body['error'] : 'Login failed.';
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Hybrid Chat Settings</h1>
        <?php
        // Show persistent FAQ sync message if set
        if ($msg = get_transient('hybrid_chat_faq_sync_msg')) {
            echo $msg;
            delete_transient('hybrid_chat_faq_sync_msg');
        }
        ?>
        <?php if (!$site_id || !$jwt): ?>
            <form method="post">
                <h2>Client Login</h2>
                <?php if ($login_error) echo '<div class="error"><p>' . esc_html($login_error) . '</p></div>'; ?>
                <table class="form-table"><tbody>
                    <tr><th><label for="hybrid_chat_email">Email</label></th><td><input type="email" name="hybrid_chat_email" required></td></tr>
                    <tr><th><label for="hybrid_chat_password">Password</label></th><td><input type="password" name="hybrid_chat_password" required></td></tr>
                </tbody></table>
                <p><input type="submit" name="hybrid_chat_login" class="button button-primary" value="Login"></p>
            </form>
        <?php else: ?>
            <?php $current_tab = isset($_GET['tab']) ? esc_attr($_GET['tab']) : 'general'; ?>
            <h2 class="nav-tab-wrapper">
                <a href="?page=hybrid-chat&tab=general" class="nav-tab<?php if ($current_tab == 'general') echo ' nav-tab-active'; ?>">General</a>
                <a href="?page=hybrid-chat&tab=appearance" class="nav-tab<?php if ($current_tab == 'appearance') echo ' nav-tab-active'; ?>">Appearance</a>
                <a href="?page=hybrid-chat&tab=ai_training" class="nav-tab<?php if ($current_tab == 'ai_training') echo ' nav-tab-active'; ?>">AI Training</a>
            </h2>
            <form method="post" action="options.php" enctype="multipart/form-data">
                <?php
                $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'general';
                $settings_group = $active_tab === 'appearance' ? 'hybrid_chat_appearance' : ($active_tab === 'ai_training' ? 'hybrid_chat_ai_training' : 'hybrid_chat_general');
                settings_fields($settings_group);
                if ($active_tab == 'general') {
                    do_settings_sections('hybrid_chat');
                } elseif ($active_tab == 'appearance') {
                    do_settings_sections('hybrid_chat_appearance');
                } elseif ($active_tab == 'ai_training') {
                    do_settings_sections('hybrid_chat_ai_training');
                }
                submit_button();
                ?>
            </form>
            <form method="post"><input type="submit" name="hybrid_chat_logout" class="button" value="Logout"></form>
        <?php endif; ?>
    </div>
    <?php
    // Handle logout
    if (isset($_POST['hybrid_chat_logout'])) {
        delete_option('hybrid_chat_jwt');
        delete_option('hybrid_chat_site_id');
        echo '<script>location.reload();</script>';
    }
}

// 2. Register settings
add_action('admin_init', function() {
    // General tab
    register_setting('hybrid_chat_general', 'hybrid_chat_bot_name');
    register_setting('hybrid_chat_general', 'hybrid_chat_welcome');
    add_settings_section('hybrid_chat_section', '', null, 'hybrid_chat');
    add_settings_field('hybrid_chat_bot_name', 'Bot Name', function() {
        echo '<input type="text" name="hybrid_chat_bot_name" value="' . esc_attr(get_option('hybrid_chat_bot_name')) . '" />';
    }, 'hybrid_chat', 'hybrid_chat_section');
    add_settings_field('hybrid_chat_welcome', 'Welcome Message', function() {
        echo '<input type="text" name="hybrid_chat_welcome" value="' . esc_attr(get_option('hybrid_chat_welcome')) . '" />';
    }, 'hybrid_chat', 'hybrid_chat_section');

    // Appearance tab
    register_setting('hybrid_chat_appearance', 'hybrid_chat_style');
    add_settings_section('hybrid_chat_appearance', '', null, 'hybrid_chat_appearance');
    add_settings_field('hybrid_chat_style', 'Chatbot Style', function() {
        $current_style = get_option('hybrid_chat_style', 'sleek');
        $styles = ['sleek' => 'Sleek', 'glassmorphism' => 'Glassmorphism', 'minimal' => 'Minimal', 'corporate' => 'Corporate', 'playful' => 'Playful', 'liquid-glass' => 'Liquid Glass','dark-modern' => 'Dark Modern','sunset-glow' => 'Sunset Glow', 'oceanic' =>'Oceanic' ];
        echo '<select name="hybrid_chat_style">';
        foreach ($styles as $key => $label) {
            echo '<option value="' . esc_attr($key) . '"' . selected($current_style, $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
    }, 'hybrid_chat_appearance', 'hybrid_chat_appearance');
    // AI Training tab
    register_setting('hybrid_chat_ai_training', 'hybrid_chat_faq');
    add_settings_section('hybrid_chat_ai_training_section', '', null, 'hybrid_chat_ai_training');
    add_settings_field('hybrid_chat_faq', 'FAQ Questions & Answers', function() {
        // Fetch FAQ from backend before displaying
        $site_id = get_option('hybrid_chat_site_id');
        $jwt = get_option('hybrid_chat_jwt');
        if ($site_id && $jwt && !isset($_POST['option_page'])) {
            $faq_url = 'https://echo-ai-chat-server.onrender.com/faq?siteId=' . urlencode($site_id);
            $faq_response = wp_remote_get($faq_url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $jwt
                ]
            ]);
            if (!is_wp_error($faq_response)) {
                $faq_data = json_decode(wp_remote_retrieve_body($faq_response), true);
                if (!empty($faq_data['faqContent'])) {
                    update_option('hybrid_chat_faq', $faq_data['faqContent']);
                }
            }
        }
        // Handle file upload if present
        if (isset($_FILES['faq_file']) && $_FILES['faq_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['faq_file']['tmp_name'];
            $file_type = $_FILES['faq_file']['type'];
            $ext = strtolower(pathinfo($_FILES['faq_file']['name'], PATHINFO_EXTENSION));
            $content = '';
            if ($ext === 'txt') {
                $content = file_get_contents($file_tmp);
            } elseif ($ext === 'pdf') {
                if (class_exists('Smalot\PdfParser\Parser')) {
                    $parser = new Smalot\PdfParser\Parser();
                    $pdf = $parser->parseFile($file_tmp);
                    $content = $pdf->getText();
                } else {
                    $content = 'PDF parsing library not installed.';
                }
            } elseif ($ext === 'docx') {
                if (class_exists('PhpOffice\\PhpWord\\IOFactory')) {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_tmp);
                    $text = '';
                    foreach ($phpWord->getSections() as $section) {
                        $elements = $section->getElements();
                        foreach ($elements as $element) {
                            if (method_exists($element, 'getText')) {
                                $text .= $element->getText() . "\n";
                            }
                        }
                    }
                    $content = $text;
                } else {
                    $content = 'DOCX parsing library not installed.';
                }
            }
            if ($content && strpos($content, 'parsing library not installed') === false) {
                update_option('hybrid_chat_faq', $content);
                echo '<div class="updated"><p>FAQ data loaded from file.</p></div>';
                hybrid_chat_sync_faq_to_backend($content);
            } else {
                echo '<div class="error"><p>' . esc_html($content) . '</p></div>';
            }
        }
        // Always sync FAQ to backend when the AI Training tab is saved
        if (
            ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hybrid_chat_faq'])) ||
            (isset($_POST['option_page']) && $_POST['option_page'] === 'hybrid_chat_ai_training' && isset($_POST['hybrid_chat_faq']))
        ) {
            $sync_response = hybrid_chat_sync_faq_to_backend($_POST['hybrid_chat_faq']);
            $msg = '';
            if (is_wp_error($sync_response)) {
                $msg = '<div class="error"><p>FAQ sync failed: ' . esc_html($sync_response->get_error_message()) . '</p></div>';
            } elseif ($sync_response) {
                $body = wp_remote_retrieve_body($sync_response);
                $result = json_decode($body, true);
                if (!empty($result['success'])) {
                    $msg = '<div class="updated"><p>FAQ synced to backend successfully.</p></div>';
                } else {
                    $msg = '<div class="error"><p>FAQ sync error: ' . esc_html($body) . '</p></div>';
                }
            }
            set_transient('hybrid_chat_faq_sync_msg', $msg, 30);
            // Redirect to avoid resubmission and show message
            wp_redirect(admin_url('admin.php?page=hybrid-chat&tab=ai_training'));
            exit;
        }
        echo '<textarea name="hybrid_chat_faq" rows="10" style="width:100%">' . esc_textarea(get_option('hybrid_chat_faq')) . '</textarea>';
        echo '<p class="description">Enter FAQ as Q: ... A: ... blocks, one per line or paragraph.</p>';
        echo '<input type="file" name="faq_file" accept=".txt,.pdf,.docx" />';
        echo '<p class="description">Upload a .txt, .pdf, or .docx file to import FAQ data.</p>';
    }, 'hybrid_chat_ai_training', 'hybrid_chat_ai_training_section');

    // Sync bot settings on save
    add_action('update_option_hybrid_chat_bot_name', 'hybrid_chat_sync_settings_to_backend', 10, 2);
    add_action('update_option_hybrid_chat_welcome', 'hybrid_chat_sync_settings_to_backend', 10, 2);
    add_action('update_option_hybrid_chat_style', 'hybrid_chat_sync_settings_to_backend', 10, 2);
});

// Helper: Sync FAQ to backend
function hybrid_chat_sync_faq_to_backend($faq_content) {
    $site_id = get_option('hybrid_chat_site_id');
    $jwt = get_option('hybrid_chat_jwt');
    if (!$site_id || !$jwt) return;
    $api_url = 'https://echo-ai-chat-server.onrender.com/faq'; // Changed to deployed backend URL
    $body = [
        'siteId' => $site_id,
        'faqContent' => $faq_content,
        'structuredFaq' => []
    ];
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $jwt
        ],
        'body' => json_encode($body)
    ];
    $response = wp_remote_post($api_url, $args);
    // Debug output for FAQ sync
    if (is_wp_error($response)) {
        error_log('Hybrid Chat FAQ sync error: ' . $response->get_error_message());
        return $response;
    } else {
        error_log('Hybrid Chat FAQ sync payload: ' . json_encode($body));
        error_log('Hybrid Chat FAQ sync response: ' . wp_remote_retrieve_body($response));
        return $response;
    }
}

// Helper: Sync bot settings to backend
function hybrid_chat_sync_settings_to_backend($old_value, $value) {
    $site_id = get_option('hybrid_chat_site_id');
    $jwt = get_option('hybrid_chat_jwt');
    if (!$site_id || !$jwt) return;
    $api_url = 'https://echo-ai-chat-server.onrender.com/config'; // Changed to deployed backend URL
    $body = [
        'siteId' => $site_id,
        'botName' => get_option('hybrid_chat_bot_name'),
        'welcomeMessage' => get_option('hybrid_chat_welcome'),
        'style' => get_option('hybrid_chat_style')
    ];
    $args = [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $jwt
        ],
        'body' => json_encode($body)
    ];
    wp_remote_post($api_url, $args);
}

// 3. Output the script tag with config
add_action('wp_footer', function() {
    $bot_name = esc_attr(get_option('hybrid_chat_bot_name'));
    $welcome = esc_attr(get_option('hybrid_chat_welcome'));
    $style = esc_attr(get_option('hybrid_chat_style', 'sleek'));
    $site_id = esc_attr(get_option('hybrid_chat_site_id'));
    ?>
    <script
        src="https://hybot-frontend.vercel.app/widget.js"
        data-bot-name="<?php echo $bot_name; ?>"
        data-welcome="<?php echo $welcome; ?>"
        data-style="<?php echo $style; ?>"
        data-site-id="<?php echo $site_id; ?>"
    ></script>
    <?php
});
