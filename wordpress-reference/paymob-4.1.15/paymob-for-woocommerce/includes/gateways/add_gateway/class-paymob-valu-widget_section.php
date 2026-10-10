<?php

class Paymob_Valu_Widget_Settings
{
    public static function paymob_valu_widget_setting($settings, $current_section) {
        static $already_added = false; // Prevent duplicate merging

        if ('valu_widget' === $current_section && !$already_added) {
            $already_added = true; // Mark as processed

            $new_settings = include __DIR__ . '/../../admin/paymob-valu_widget_setting.php';

            if (is_array($new_settings)) {
                foreach ($new_settings as $new_setting) {
                    // Ensure the setting ID does not already exist
                    if (!in_array($new_setting, $settings)) {
                        $settings[] = $new_setting;
                    }
                }
            }

			Paymob_Style::paymob_admin();
            Paymob_Scripts::enqueue_paymob_widget_script();
        }

        return $settings;
    }
}
