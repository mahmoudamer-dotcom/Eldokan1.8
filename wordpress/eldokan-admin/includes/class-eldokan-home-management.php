<?php
if (!defined('ABSPATH')) exit;

/**
 * Structured Home content owned by ElDokan Admin.
 *
 * Private CPTs keep each editorial record independently auditable and avoid a
 * page-builder or one large serialized content blob.
 */
final class ElDokan_Home_Management {
    const SLIDE_TYPE = 'eldokan_home_slide';
    const BANNER_TYPE = 'eldokan_home_banner';
    const SECTIONS_OPTION = 'eldokan_home_sections_v1';
    const HOME_GENERATION_OPTION = 'eldokan_customer_api_home_generation';

    public function __construct() {
        add_action('init', [$this, 'register_content_types']);
        add_action('admin_menu', [$this, 'register_menu'], 36);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_post_eldokan_home_save_item', [$this, 'save_item']);
        add_action('admin_post_eldokan_home_delete_item', [$this, 'delete_item']);
        add_action('admin_post_eldokan_home_save_sections', [$this, 'save_sections']);
    }

    public function register_content_types() {
        $common = [
            'public' => false,
            'show_ui' => false,
            'show_in_rest' => false,
            'supports' => ['title'],
            'map_meta_cap' => true,
            'capability_type' => 'post',
        ];
        register_post_type(self::SLIDE_TYPE, array_merge($common, [
            'labels' => ['name' => 'Home slides', 'singular_name' => 'Home slide'],
        ]));
        register_post_type(self::BANNER_TYPE, array_merge($common, [
            'labels' => ['name' => 'Home banners', 'singular_name' => 'Home banner'],
        ]));
    }

    public function register_menu() {
        add_submenu_page(
            'eldokan-admin',
            'Home Management',
            'Home Management',
            'eldokan_backoffice_view_home',
            'eldokan-admin-home',
            [$this, 'render_page']
        );
    }

    public function assets($hook) {
        if (strpos((string) $hook, 'eldokan-admin-home') === false) return;
        wp_enqueue_media();
        wp_enqueue_script(
            'eldokan-home-management',
            ELDOKAN_ADMIN_URL . 'assets/home-management.js',
            ['jquery'],
            ELDOKAN_ADMIN_VERSION,
            true
        );
        wp_localize_script('eldokan-home-management', 'eldokanHomeUI', ['title' => __('Choose Home image', 'eldokan-admin'), 'use' => __('Use this image', 'eldokan-admin')]);
    }

    private static function default_sections() {
        return [
            'hero_slider' => ['enabled' => true, 'order' => 1, 'title_ar' => '', 'title_en' => ''],
            'categories' => ['enabled' => true, 'order' => 2, 'title_ar' => 'تسوق حسب القسم', 'title_en' => 'Shop by Category'],
            'banner_grid' => ['enabled' => true, 'order' => 3, 'title_ar' => '', 'title_en' => ''],
            'featured_deals' => ['enabled' => true, 'order' => 4, 'title_ar' => 'عروض مميزة', 'title_en' => 'Featured Deals'],
            'best_sellers' => ['enabled' => true, 'order' => 5, 'title_ar' => 'الأكثر مبيعًا', 'title_en' => 'Best Sellers'],
            'new_arrivals' => ['enabled' => true, 'order' => 6, 'title_ar' => 'وصل حديثًا', 'title_en' => 'New Arrivals'],
            'brands' => ['enabled' => true, 'order' => 7, 'title_ar' => 'علامات تجارية مميزة', 'title_en' => 'Featured Brands'],
        ];
    }

    public static function sections() {
        $stored = get_option(self::SECTIONS_OPTION, []);
        $result = self::default_sections();
        if (is_array($stored)) {
            foreach ($result as $id => $defaults) {
                if (isset($stored[$id]) && is_array($stored[$id])) {
                    $result[$id] = wp_parse_args($stored[$id], $defaults);
                }
            }
        }
        return $result;
    }

    private function assert_manage() {
        if (!current_user_can('eldokan_backoffice_manage_home')) {
            wp_die(esc_html__('You are not allowed to manage Home content.', 'eldokan-admin'), 403);
        }
    }

    private function item_type($entity) {
        return $entity === 'banner' ? self::BANNER_TYPE : ($entity === 'slide' ? self::SLIDE_TYPE : '');
    }

    private function sanitize_attachment($value) {
        $id = absint($value);
        if (!$id || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) {
            return 0;
        }
        return $id;
    }

    private function sanitize_cta_value($type, $value) {
        $value = trim((string) $value);
        if ($type === 'url') return esc_url_raw($value);
        if ($type === 'product') return preg_match('/^prd_[1-9][0-9]*$/', $value) ? $value : '';
        if ($type === 'category') return sanitize_title($value);
        if ($type === 'search') return sanitize_text_field($value);
        return '';
    }

    private function sanitize_datetime($value) {
        $value = trim((string) $value);
        if ($value === '') return '';
        $date = DateTimeImmutable::createFromFormat('Y-m-d\\TH:i', $value, wp_timezone());
        return $date ? $date->format('Y-m-d H:i:s') : '';
    }

    public function save_item() {
        $this->assert_manage();
        check_admin_referer('eldokan_home_save_item');

        $entity = sanitize_key(wp_unslash($_POST['entity'] ?? ''));
        $post_type = $this->item_type($entity);
        if (!$post_type) wp_die(esc_html__('Invalid Home content type.', 'eldokan-admin'), 400);

        $post_id = absint($_POST['item_id'] ?? 0);
        if ($post_id && get_post_type($post_id) !== $post_type) {
            wp_die(esc_html__('Invalid Home content record.', 'eldokan-admin'), 400);
        }

        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        if ($title === '') wp_die(esc_html__('Title is required.', 'eldokan-admin'), 400);

        $saved = wp_insert_post([
            'ID' => $post_id,
            'post_type' => $post_type,
            'post_status' => 'publish',
            'post_title' => $title,
        ], true);
        if (is_wp_error($saved)) wp_die(esc_html($saved->get_error_message()), 500);

        $cta_type = sanitize_key(wp_unslash($_POST['cta_type'] ?? 'none'));
        if (!in_array($cta_type, ['none', 'url', 'product', 'category', 'search'], true)) $cta_type = 'none';
        $lang = sanitize_key(wp_unslash($_POST['lang'] ?? 'ar'));
        if (!in_array($lang, ['ar', 'en'], true)) $lang = 'ar';

        $meta = [
            '_eldokan_home_subtitle' => sanitize_textarea_field(wp_unslash($_POST['subtitle'] ?? '')),
            '_eldokan_home_desktop_image_id' => $this->sanitize_attachment($_POST['desktop_image_id'] ?? 0),
            '_eldokan_home_mobile_image_id' => $this->sanitize_attachment($_POST['mobile_image_id'] ?? 0),
            '_eldokan_home_cta_text' => sanitize_text_field(wp_unslash($_POST['cta_text'] ?? '')),
            '_eldokan_home_cta_type' => $cta_type,
            '_eldokan_home_cta_value' => $this->sanitize_cta_value($cta_type, wp_unslash($_POST['cta_value'] ?? '')),
            '_eldokan_home_lang' => $lang,
            '_eldokan_home_enabled' => !empty($_POST['enabled']) ? '1' : '0',
            '_eldokan_home_order' => absint($_POST['item_order'] ?? 0),
            '_eldokan_home_starts_at' => $this->sanitize_datetime(wp_unslash($_POST['starts_at'] ?? '')),
            '_eldokan_home_ends_at' => $this->sanitize_datetime(wp_unslash($_POST['ends_at'] ?? '')),
        ];
        foreach ($meta as $key => $value) update_post_meta($saved, $key, $value);

        self::invalidate_home_cache();
        do_action('eldokan_admin_home_audit', $post_id ? 'home_item_updated' : 'home_item_created', sprintf('%s #%d saved.', ucfirst($entity), $saved), [
            'object_type' => $post_type,
            'object_id' => $saved,
            'meta' => ['language' => $lang, 'enabled' => $meta['_eldokan_home_enabled'] === '1'],
        ]);
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-home&saved=1'));
        exit;
    }

    public function delete_item() {
        $this->assert_manage();
        $post_id = absint($_GET['item_id'] ?? 0);
        check_admin_referer('eldokan_home_delete_item_' . $post_id);
        $type = get_post_type($post_id);
        if (!in_array($type, [self::SLIDE_TYPE, self::BANNER_TYPE], true)) {
            wp_die(esc_html__('Invalid Home content record.', 'eldokan-admin'), 400);
        }
        wp_delete_post($post_id, true);
        self::invalidate_home_cache();
        do_action('eldokan_admin_home_audit', 'home_item_deleted', sprintf('Home content #%d deleted.', $post_id), [
            'object_type' => $type, 'object_id' => $post_id,
        ]);
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-home&deleted=1'));
        exit;
    }

    public function save_sections() {
        $this->assert_manage();
        check_admin_referer('eldokan_home_save_sections');
        $input = isset($_POST['sections']) && is_array($_POST['sections']) ? wp_unslash($_POST['sections']) : [];
        $clean = [];
        foreach (self::default_sections() as $id => $defaults) {
            $row = isset($input[$id]) && is_array($input[$id]) ? $input[$id] : [];
            $clean[$id] = [
                'enabled' => !empty($row['enabled']),
                'order' => absint($row['order'] ?? $defaults['order']),
                'title_ar' => sanitize_text_field($row['title_ar'] ?? $defaults['title_ar']),
                'title_en' => sanitize_text_field($row['title_en'] ?? $defaults['title_en']),
            ];
        }
        update_option(self::SECTIONS_OPTION, $clean, false);
        self::invalidate_home_cache();
        do_action('eldokan_admin_home_audit', 'home_sections_updated', 'Home section settings updated.', [
            'meta' => ['sections' => array_keys($clean)],
        ]);
        wp_safe_redirect(admin_url('admin.php?page=eldokan-admin-home&saved=1'));
        exit;
    }

    public static function invalidate_home_cache() {
        $generation = max(1, absint(get_option(self::HOME_GENERATION_OPTION, 1))) + 1;
        update_option(self::HOME_GENERATION_OPTION, $generation, false);
    }

    private function field_value($post_id, $key, $default='') {
        if (!$post_id) return $default;
        $value = get_post_meta($post_id, $key, true);
        return $value === '' ? $default : $value;
    }

    private function render_item_form($entity, $post=null) {
        if (!current_user_can('eldokan_backoffice_manage_home')) return;
        $id = $post ? $post->ID : 0;
        $label = $entity === 'slide' ? 'Hero slide' : 'Promo banner';
        $enabled = $this->field_value($id, '_eldokan_home_enabled', '1') === '1';
        ?>
        <div class="eda-card">
          <h2><?php echo esc_html(translate($id ? 'Edit ' . $label : 'Add ' . $label, 'eldokan-admin')); ?></h2>
          <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="eda-form">
            <input type="hidden" name="action" value="eldokan_home_save_item">
            <input type="hidden" name="entity" value="<?php echo esc_attr($entity); ?>">
            <input type="hidden" name="item_id" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('eldokan_home_save_item'); ?>
            <label><?php echo esc_html__('Title', 'eldokan-admin'); ?><input required type="text" name="title" value="<?php echo esc_attr($post ? $post->post_title : ''); ?>"></label>
            <label><?php echo esc_html__('Subtitle', 'eldokan-admin'); ?><textarea name="subtitle"><?php echo esc_textarea($this->field_value($id, '_eldokan_home_subtitle')); ?></textarea></label>
            <label><?php echo esc_html__('Desktop image attachment ID', 'eldokan-admin'); ?><input id="eldokan-desktop-image-<?php echo esc_attr($entity); ?>" type="number" min="1" name="desktop_image_id" value="<?php echo esc_attr($this->field_value($id, '_eldokan_home_desktop_image_id')); ?>"><button type="button" class="button eldokan-home-media" data-target="eldokan-desktop-image-<?php echo esc_attr($entity); ?>"><?php echo esc_html__('Choose image', 'eldokan-admin'); ?></button></label>
            <label><?php echo esc_html__('Mobile image attachment ID', 'eldokan-admin'); ?><input id="eldokan-mobile-image-<?php echo esc_attr($entity); ?>" type="number" min="1" name="mobile_image_id" value="<?php echo esc_attr($this->field_value($id, '_eldokan_home_mobile_image_id')); ?>"><button type="button" class="button eldokan-home-media" data-target="eldokan-mobile-image-<?php echo esc_attr($entity); ?>"><?php echo esc_html__('Choose image', 'eldokan-admin'); ?></button></label>
            <label><?php echo esc_html__('CTA text', 'eldokan-admin'); ?><input type="text" name="cta_text" value="<?php echo esc_attr($this->field_value($id, '_eldokan_home_cta_text')); ?>"></label>
            <label><?php echo esc_html__('CTA type', 'eldokan-admin'); ?><select name="cta_type">
              <?php foreach (['none','url','product','category','search'] as $value): ?>
              <option value="<?php echo esc_attr($value); ?>" <?php selected($this->field_value($id, '_eldokan_home_cta_type', 'none'), $value); ?>><?php echo esc_html($value); ?></option>
              <?php endforeach; ?>
            </select></label>
            <label><?php echo esc_html__('CTA value', 'eldokan-admin'); ?><input type="text" name="cta_value" value="<?php echo esc_attr($this->field_value($id, '_eldokan_home_cta_value')); ?>"></label>
            <label><?php echo esc_html__('Language', 'eldokan-admin'); ?><select name="lang"><option value="ar" <?php selected($this->field_value($id, '_eldokan_home_lang', 'ar'), 'ar'); ?>><?php echo esc_html__('Arabic', 'eldokan-admin'); ?></option><option value="en" <?php selected($this->field_value($id, '_eldokan_home_lang'), 'en'); ?>><?php echo esc_html__('English', 'eldokan-admin'); ?></option></select></label>
            <label><?php echo esc_html__('Order', 'eldokan-admin'); ?><input type="number" min="0" name="item_order" value="<?php echo esc_attr($this->field_value($id, '_eldokan_home_order', 0)); ?>"></label>
            <label><?php echo esc_html__('Starts at', 'eldokan-admin'); ?><input type="datetime-local" name="starts_at" value="<?php echo esc_attr(str_replace(' ', 'T', $this->field_value($id, '_eldokan_home_starts_at'))); ?>"></label>
            <label><?php echo esc_html__('Ends at', 'eldokan-admin'); ?><input type="datetime-local" name="ends_at" value="<?php echo esc_attr(str_replace(' ', 'T', $this->field_value($id, '_eldokan_home_ends_at'))); ?>"></label>
            <label><input type="checkbox" name="enabled" value="1" <?php checked($enabled); ?>> <?php echo esc_html__('Enabled', 'eldokan-admin'); ?></label>
            <p><button class="button button-primary" type="submit"><?php echo esc_html(translate('Save ' . $label, 'eldokan-admin')); ?></button></p>
          </form>
        </div>
        <?php
    }

    private function render_items($entity) {
        $type = $this->item_type($entity);
        $posts = get_posts(['post_type' => $type, 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_eldokan_home_order', 'orderby' => 'meta_value_num', 'order' => 'ASC']);
        echo '<div class="eda-card"><h2>' . esc_html(translate($entity === 'slide' ? 'Hero slides' : 'Promo banners', 'eldokan-admin')) . '</h2>';
        if (!$posts) echo '<p class="description">' . esc_html__('No records yet.', 'eldokan-admin') . '</p>';
        foreach ($posts as $post) {
            $edit = admin_url('admin.php?page=eldokan-admin-home&edit=' . $post->ID . '&entity=' . $entity);
            echo '<p><strong>' . esc_html($post->post_title) . '</strong> — ' . esc_html(get_post_meta($post->ID, '_eldokan_home_lang', true)) . ' — order ' . esc_html(get_post_meta($post->ID, '_eldokan_home_order', true));
            echo ' <a href="' . esc_url($edit) . '">' . esc_html__('Edit', 'eldokan-admin') . '</a>';
            if (current_user_can('eldokan_backoffice_manage_home')) {
                $delete = wp_nonce_url(admin_url('admin-post.php?action=eldokan_home_delete_item&item_id=' . $post->ID), 'eldokan_home_delete_item_' . $post->ID);
                echo ' | <a class="submitdelete" href="' . esc_url($delete) . '">' . esc_html__('Delete', 'eldokan-admin') . '</a>';
            }
            echo '</p>';
        }
        echo '</div>';
    }

    public function render_page() {
        if (!current_user_can('eldokan_backoffice_view_home')) wp_die(esc_html__('You are not allowed to view Home content.', 'eldokan-admin'), 403);
        $edit_id = absint($_GET['edit'] ?? 0);
        $entity = sanitize_key($_GET['entity'] ?? 'slide');
        $edit_post = $edit_id ? get_post($edit_id) : null;
        if ($edit_post && get_post_type($edit_post) !== $this->item_type($entity)) $edit_post = null;
        $sections = self::sections();
        ?>
        <div class="wrap eda">
          <div class="eda-hero"><div><p class="eda-eyebrow"><?php echo esc_html__('Storefront content', 'eldokan-admin'); ?></p><h1><?php echo esc_html__('Home Management', 'eldokan-admin'); ?></h1><p><?php echo esc_html__('Structured, bilingual Home content delivered through Customer API Contract v1.', 'eldokan-admin'); ?></p></div></div>
          <?php if (isset($_GET['saved'])): ?><div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Home content saved and its cache invalidated.', 'eldokan-admin'); ?></p></div><?php endif; ?>
          <div class="eda-two">
            <?php $this->render_items('slide'); $this->render_items('banner'); ?>
          </div>
          <?php $this->render_item_form($entity, $edit_post); ?>
          <?php if (!$edit_post) $this->render_item_form($entity === 'slide' ? 'banner' : 'slide'); ?>
          <div class="eda-card">
            <h2><?php echo esc_html__('Home sections', 'eldokan-admin'); ?></h2>
            <p class="description"><?php echo esc_html__('These settings control visibility, order and localized headings. Dynamic catalog content is not duplicated here.', 'eldokan-admin'); ?></p>
            <?php if (current_user_can('eldokan_backoffice_manage_home')): ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
              <input type="hidden" name="action" value="eldokan_home_save_sections"><?php wp_nonce_field('eldokan_home_save_sections'); ?>
              <table class="widefat striped"><thead><tr><th><?php echo esc_html__('Section', 'eldokan-admin'); ?></th><th><?php echo esc_html__('Enabled', 'eldokan-admin'); ?></th><th><?php echo esc_html__('Order', 'eldokan-admin'); ?></th><th><?php echo esc_html__('Arabic title', 'eldokan-admin'); ?></th><th><?php echo esc_html__('English title', 'eldokan-admin'); ?></th></tr></thead><tbody>
              <?php foreach ($sections as $id => $row): ?><tr>
                <td><code><?php echo esc_html($id); ?></code></td>
                <td><input type="checkbox" name="sections[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked(!empty($row['enabled'])); ?>></td>
                <td><input type="number" min="0" name="sections[<?php echo esc_attr($id); ?>][order]" value="<?php echo esc_attr($row['order']); ?>"></td>
                <td><input type="text" dir="rtl" name="sections[<?php echo esc_attr($id); ?>][title_ar]" value="<?php echo esc_attr($row['title_ar']); ?>"></td>
                <td><input type="text" name="sections[<?php echo esc_attr($id); ?>][title_en]" value="<?php echo esc_attr($row['title_en']); ?>"></td>
              </tr><?php endforeach; ?></tbody></table>
              <p><button class="button button-primary" type="submit"><?php echo esc_html__('Save section settings', 'eldokan-admin'); ?></button></p>
            </form>
            <?php else: ?><p><?php echo esc_html__('You have read-only access.', 'eldokan-admin'); ?></p><?php endif; ?>
          </div>
        </div>
        <?php
    }
}
