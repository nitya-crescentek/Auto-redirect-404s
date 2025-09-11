<?php
/**
 * Admin settings template
 *
 * @package Redirect404Custom
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('404 Redirect Settings', 'redirect-404-custom'); ?></h1>
    
    <?php settings_errors('r404c_messages'); ?>
    
    <div class="r404c-container">
        <div class="r404c-main-content">
            <form method="post" action="">
                <?php wp_nonce_field('r404c_save_settings', 'r404c_nonce'); ?>
                
                <div class="r404c-section">
                    <div class="r404c-section-header">
                        <h2><?php _e('Redirect Configuration', 'redirect-404-custom'); ?></h2>
                        <p class="description"><?php _e('Configure how 404 errors should be handled on your website.', 'redirect-404-custom'); ?></p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="r404c_enabled"><?php _e('Enable Redirects', 'redirect-404-custom'); ?></label>
                            </th>
                            <td>
                                <div class="r404c-toggle-container">
                                    <label class="r404c-toggle">
                                        <input type="checkbox" 
                                               id="r404c_enabled" 
                                               name="r404c_enabled" 
                                               <?php checked($enabled, 'on'); ?> />
                                        <span class="r404c-toggle-slider"></span>
                                        
                                    </label>
                                    <span class="r404c-toggle-label" data-on="<?php _e('Enabled', 'redirect-404-custom'); ?>" data-off="<?php _e('Disabled', 'redirect-404-custom'); ?>"></span>
                                </div>
                                <p class="description"><?php _e('Toggle this to enable or disable 404 redirects.', 'redirect-404-custom'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="r404c_redirect_url"><?php _e('Redirect URL', 'redirect-404-custom'); ?></label>
                            </th>
                            <td>
                                <div class="r404c-url-section">
                                    <div class="r404c-quick-select">
                                        <label for="r404c_quick_select"><?php _e('Quick Select:', 'redirect-404-custom'); ?></label>
                                        <select id="r404c_quick_select">
                                            <option value=""><?php _e('-- Choose a page --', 'redirect-404-custom'); ?></option>
                                            <option value="<?php echo esc_url(home_url()); ?>"><?php _e('Home Page', 'redirect-404-custom'); ?></option>
                                            <?php foreach ($pages as $page) : ?>
                                                <option value="<?php echo esc_url(get_permalink($page->ID)); ?>">
                                                    <?php echo esc_html($page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="r404c-url-input">
                                        <input type="url" 
                                               id="r404c_redirect_url" 
                                               name="r404c_redirect_url" 
                                               value="<?php echo esc_url($redirect_url); ?>" 
                                               class="large-text" 
                                               placeholder="<?php _e('Enter redirect URL...', 'redirect-404-custom'); ?>" />
                                    </div>
                                </div>
                                <p class="description"><?php _e('404 pages will redirect to this URL. Use the dropdown above for quick selection or enter a custom URL.', 'redirect-404-custom'); ?></p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php _e('Redirect Type', 'redirect-404-custom'); ?></th>
                            <td>
                                <div class="r404c-radio-group">
                                    <label class="r404c-radio-option">
                                        <input type="radio" 
                                               name="r404c_redirect_type" 
                                               value="301" 
                                               <?php checked($redirect_type, '301'); ?> />
                                        <span class="r404c-radio-checkmark"></span>
                                        <span class="r404c-radio-label">
                                            <strong><?php _e('301 - Permanent Redirect', 'redirect-404-custom'); ?></strong>
                                            <small><?php _e('(Recommended for SEO)', 'redirect-404-custom'); ?></small>
                                        </span>
                                    </label>
                                    
                                    <label class="r404c-radio-option">
                                        <input type="radio" 
                                               name="r404c_redirect_type" 
                                               value="302" 
                                               <?php checked($redirect_type, '302'); ?> />
                                        <span class="r404c-radio-checkmark"></span>
                                        <span class="r404c-radio-label">
                                            <strong><?php _e('302 - Temporary Redirect', 'redirect-404-custom'); ?></strong>
                                            <small><?php _e('(Use if the redirect is temporary)', 'redirect-404-custom'); ?></small>
                                        </span>
                                    </label>
                                </div>
                                <p class="description"><?php _e('301 redirects are permanent and pass SEO value, while 302 redirects are temporary.', 'redirect-404-custom'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save Settings', 'redirect-404-custom'), 'primary', 'submit', false); ?>
            </form>
        </div>
        
        <div class="r404c-sidebar">
            <div class="r404c-sidebar-box">
                <h3><?php _e('How it Works', 'redirect-404-custom'); ?></h3>
                <ul>
                    <li><?php _e('When a visitor accesses a non-existent page, they get a 404 error', 'redirect-404-custom'); ?></li>
                    <li><?php _e('This plugin automatically redirects them to your chosen URL', 'redirect-404-custom'); ?></li>
                    <li><?php _e('This improves user experience and helps with SEO', 'redirect-404-custom'); ?></li>
                </ul>
            </div>
            
            <div class="r404c-sidebar-box">
                <h3><?php _e('SEO Benefits', 'redirect-404-custom'); ?></h3>
                <ul>
                    <li><?php _e('Reduces bounce rate from 404 errors', 'redirect-404-custom'); ?></li>
                    <li><?php _e('Helps with Google Search Console reports', 'redirect-404-custom'); ?></li>
                    <li><?php _e('Keeps visitors on your site longer', 'redirect-404-custom'); ?></li>
                </ul>
            </div>
            
            <div class="r404c-sidebar-box r404c-status-box">
                <h3><?php _e('Current Status', 'redirect-404-custom'); ?></h3>
                <div class="r404c-status">
                    <span class="r404c-status-indicator <?php echo $enabled === 'on' ? 'active' : 'inactive'; ?>"></span>
                    <span class="r404c-status-text">
                        <?php echo $enabled === 'on' ? __('Active', 'redirect-404-custom') : __('Inactive', 'redirect-404-custom'); ?>
                    </span>
                </div>
                <?php if ($enabled === 'on') : ?>
                    <p><small><?php printf(__('Redirecting to: %s', 'redirect-404-custom'), '<br><strong>' . esc_url($redirect_url) . '</strong>'); ?></small></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>