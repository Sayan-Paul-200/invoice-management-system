<?php
namespace IMS;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle Elementor Pro form submissions to update an existing ac_invoice post.
 *
 * This expects the Elementor Form to contain a field with ID 'invoice_id' (text or numeric).
 * If the value is numeric and corresponds to an existing ac_invoice post ID, that post is updated.
 * Otherwise we attempt to find an ac_invoice by post_title (exact match) and update that.
 *
 * The form ID / name expected: 'edit_invoice_form' or 'Edit Invoice Form'
 */
if ( ! function_exists( __NAMESPACE__ . '\\ims_update_ac_invoice_from_elementor' ) ) {

    add_action( 'elementor_pro/forms/new_record', __NAMESPACE__ . '\\ims_update_ac_invoice_from_elementor', 10, 2 );

    function ims_update_ac_invoice_from_elementor( $record, $handler ) {
        try {
            // Basic guards
            if ( ! is_object( $record ) || ! method_exists( $record, 'get_form_settings' ) ) {
                return;
            }

            // Identify form by ID or name
            $form_id_or_name = $record->get_form_settings( 'form_id' ) ?: $record->get_form_settings( 'form_name' );
            if ( 'edit_invoice_form' !== $form_id_or_name && 'Edit Invoice Form' !== $form_id_or_name ) {
                return;
            }

            // Get fields (Elementor provides various shapes)
            $raw_fields = $record->get( 'fields' );
            if ( empty( $raw_fields ) ) {
                return;
            }

            // Normalize fields into $fields[ field_id ] => value
            $fields = array();

            foreach ( $raw_fields as $k => $entry ) {
                // Case A: entry is array with ['id'=>'field_id','value'=>...]
                if ( is_array( $entry ) && isset( $entry['id'] ) ) {
                    $id = $entry['id'];
                    $val = isset( $entry['value'] ) ? $entry['value'] : ( isset( $entry['url'] ) ? $entry['url'] : '' );
                    $fields[ $id ] = $val;
                    continue;
                }

                // Case B: entry is scalar but key looks like form_fields[field_id]
                if ( is_string( $k ) && preg_match( '/^form_fields\[(.+)\]$/', $k, $m ) ) {
                    $fields[ $m[1] ] = $entry;
                    continue;
                }

                // Case C: key may be the field id and entry has ['value'=>...]
                if ( is_string( $k ) && is_array( $entry ) && isset( $entry['value'] ) ) {
                    $fields[ $k ] = $entry['value'];
                    continue;
                }

                // Case D: key is the field id and entry is scalar
                if ( is_string( $k ) && ( is_scalar( $entry ) || $entry === null ) ) {
                    $fields[ $k ] = $entry;
                    continue;
                }

                // Case E: numeric-indexed list with arrays containing id/value
                if ( is_array( $entry ) && isset( $entry[0] ) && is_array( $entry[0] ) && isset( $entry[0]['id'] ) ) {
                    foreach ( $entry as $sub ) {
                        if ( isset( $sub['id'] ) ) {
                            $fields[ $sub['id'] ] = isset( $sub['value'] ) ? $sub['value'] : '';
                        }
                    }
                    continue;
                }

                // fallback: if key is string, preserve it
                if ( is_string( $k ) ) {
                    $fields[ $k ] = $entry;
                }
            }

            // Optional debug
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'IMS: elementor normalized fields: ' . print_r( $fields, true ) );
            }

            // safe getter
            $get_field = function( $id ) use ( $fields ) {
                if ( ! isset( $fields[ $id ] ) ) {
                    return null;
                }
                $v = $fields[ $id ];
                if ( is_array( $v ) ) {
                    // prefer named values commonly returned
                    if ( isset( $v['url'] ) ) return $v['url'];
                    if ( isset( $v['value'] ) ) return $v['value'];
                    if ( isset( $v['id'] ) && isset( $v['url'] ) ) return $v['url'];
                    // otherwise return first scalar element
                    foreach ( $v as $x ) {
                        if ( is_scalar( $x ) ) return $x;
                    }
                    return null;
                }
                return $v;
            };

            // Find invoice post by provided invoice_id field
            $invoice_identifier = $get_field( 'invoice_id' );
            if ( $invoice_identifier === null || $invoice_identifier === '' ) {
                // nothing to update
                return;
            }

            $invoice_post = null;

            // If numeric, try as post ID
            if ( is_numeric( $invoice_identifier ) ) {
                $pid = intval( $invoice_identifier );
                $p = get_post( $pid );
                if ( $p && $p->post_type === 'ac_invoice' ) {
                    $invoice_post = $p;
                }
            }

            // If not found yet, try exact title match
            if ( ! $invoice_post ) {
                $title_search = sanitize_text_field( (string) $invoice_identifier );
                $found = get_posts( [
                    'post_type'      => 'ac_invoice',
                    'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
                    'title'          => $title_search,
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                ] );

                if ( ! empty( $found ) && isset( $found[0] ) ) {
                    $p = get_post( $found[0] );
                    if ( $p && $p->post_type === 'ac_invoice' ) {
                        $invoice_post = $p;
                    }
                }
            }

            if ( ! $invoice_post ) {
                // Not found -> nothing to update
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( sprintf( 'IMS: invoice not found for identifier: %s', (string) $invoice_identifier ) );
                }
                return;
            }

            $post_id = intval( $invoice_post->ID );

            // Optional capability check: only allow users who can edit the post to do the update
//             if ( ! current_user_can( 'edit_post', $post_id ) ) {
//                 return;
//             }

            // Prepare to update title if provided as text (overwrite if non-empty)
            $invoice_title_field = $get_field( 'invoice_id' );
            if ( $invoice_title_field !== null && $invoice_title_field !== '' ) {
                $new_title = wp_strip_all_tags( (string) $invoice_title_field );
                wp_update_post( [
                    'ID'         => $post_id,
                    'post_title' => $new_title,
                ] );
            }

            // --- MAPPINGS (field id -> meta key) ---
            // Bill category (validate)
            $bill = $get_field( 'bill_category' );
            if ( $bill !== null ) {
                $bill_s = sanitize_text_field( (string) $bill );
                $allowed_bill = array( 'service','supply','row','amc','restoration_service','restoration_supply','restoration_row','spares','training' );
                if ( $bill_s === '' ) {
                    delete_post_meta( $post_id, '_invoice_bill_category' );
                } elseif ( in_array( $bill_s, $allowed_bill, true ) ) {
                    update_post_meta( $post_id, '_invoice_bill_category', $bill_s );
                } else {
                    delete_post_meta( $post_id, '_invoice_bill_category' );
                }
            }

            // invoice_date / submission_date
            $inv_date = $get_field( 'invoice_date' );
            if ( $inv_date !== null ) {
                update_post_meta( $post_id, '_invoice_date', sanitize_text_field( (string) $inv_date ) );
            }
            $sub_date = $get_field( 'submission_date' );
            if ( $sub_date !== null ) {
                update_post_meta( $post_id, '_submission_date', sanitize_text_field( (string) $sub_date ) );
            }

            // invoice file (may be id or URL or array returned by Elementor)
            $file_field = $get_field( 'invoice_file' );
            if ( $file_field ) {
                if ( is_numeric( $file_field ) ) {
                    $aid = intval( $file_field );
                    if ( $aid ) {
                        update_post_meta( $post_id, '_invoice_file_id', $aid );
                        $url = wp_get_attachment_url( $aid );
                        if ( $url ) update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $url ) );
                    }
                } else {
                    $file_url = esc_url_raw( (string) $file_field );
                    if ( filter_var( $file_url, FILTER_VALIDATE_URL ) ) {
                        // Attempt sideload
                        require_once ABSPATH . 'wp-admin/includes/file.php';
                        require_once ABSPATH . 'wp-admin/includes/media.php';
                        require_once ABSPATH . 'wp-admin/includes/image.php';
                        $sideloaded = media_sideload_image( $file_url, $post_id, null, 'src' );
                        // media_sideload_image returns HTML string or WP_Error; best-effort to set _invoice_file_url
                        if ( ! is_wp_error( $sideloaded ) && $sideloaded ) {
                            // get attachment ID (may require mapping)
                            $attach_id = attachment_url_to_postid( $sideloaded );
                            if ( $attach_id ) {
                                update_post_meta( $post_id, '_invoice_file_id', intval( $attach_id ) );
                                update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $sideloaded ) );
                            } else {
                                update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $sideloaded ) );
                            }
                        } else {
                            // fallback store url
                            update_post_meta( $post_id, '_invoice_file_url', esc_url_raw( $file_url ) );
                        }
                    }
                }
            }

            // milestone (int 0..100)
            $ms = $get_field( 'milestone' );
            if ( $ms !== null ) {
                $ms_i = intval( $ms );
                $ms_i = max( 0, min( 100, $ms_i ) );
                update_post_meta( $post_id, '_invoice_milestone', $ms_i );
            }

            // basic_amount
            $basic_raw = $get_field( 'basic_amount' );
            if ( $basic_raw !== null && $basic_raw !== '' ) {
                $norm = str_replace( array( ',', ' ' ), array( '', '' ), (string) $basic_raw );
                $norm = preg_replace( '/[^\d\.\-]/', '', $norm );
                if ( preg_match( '/^-?\d+(\.\d+)?$/', $norm ) ) {
                    $basic_val = floatval( $norm );
                    update_post_meta( $post_id, '_invoice_basic_amount', $basic_val );
                    update_post_meta( $post_id, '_invoice_amount', $basic_val ); // legacy
                }
            }

            // gst_pct
            $gst_raw = $get_field( 'gst_pct' );
            if ( $gst_raw !== null && $gst_raw !== '' ) {
                $gst_i = intval( $gst_raw );
                $gst_i = max( 0, min( 30, $gst_i ) );
                update_post_meta( $post_id, '_invoice_gst_percent', $gst_i );
            }

            // passed_client -> _invoice_client_amount
            $passed_raw = $get_field( 'passed_client' );
            if ( $passed_raw !== null && $passed_raw !== '' ) {
                $norm = str_replace( array( ',', ' ' ), array( '', '' ), (string) $passed_raw );
                $norm = preg_replace( '/[^\d\.\-]/', '', $norm );
                if ( preg_match( '/^-?\d+(\.\d+)?$/', $norm ) ) {
                    update_post_meta( $post_id, '_invoice_client_amount', floatval( $norm ) );
                }
            }

            // Deduction fields map
            $ded_map = array(
                'retention_amount'        => '_invoice_retention_amount',
                'gst_withheld'            => '_invoice_gst_withheld',
                'tds_amount'              => '_invoice_tds_amount',
                'gst_tds_amount'          => '_invoice_gst_tds_amount',
                'bocw_amount'             => '_invoice_bocw_amount',
                'low_depth_deduction'     => '_invoice_low_depth_deduction_amount',
                'liquidated_damages'      => '_invoice_liquidated_damages_amount',
                'sla_penalty'             => '_invoice_sla_penalty_amount',
                'penalty_amount'          => '_invoice_penalty_amount',
                'other_deduction'         => '_invoice_other_deduction_amount',
            );

            foreach ( $ded_map as $field_id => $meta_key ) {
                $val_raw = $get_field( $field_id );
                if ( $val_raw !== null && $val_raw !== '' ) {
                    $norm = str_replace( array( ',', ' ' ), array( '', '' ), (string) $val_raw );
                    $norm = preg_replace( '/[^\d\.\-]/', '', $norm );
                    if ( preg_match( '/^-?\d+(\.\d+)?$/', $norm ) ) {
                        $val = floatval( $norm );
                        if ( $val < 0 ) $val = 0.0;
                        update_post_meta( $post_id, $meta_key, $val );
                    } else {
                        delete_post_meta( $post_id, $meta_key );
                    }
                } else {
                    delete_post_meta( $post_id, $meta_key );
                }
            }

            // amount_paid_by_client -> _invoice_amount_paid
            $paid_raw = $get_field( 'amount_paid_by_client' );
            if ( $paid_raw !== null && $paid_raw !== '' ) {
                $norm = str_replace( array( ',', ' ' ), array( '', '' ), (string) $paid_raw );
                $norm = preg_replace( '/[^\d\.\-]/', '', $norm );
                if ( preg_match( '/^-?\d+(\.\d+)?$/', $norm ) ) {
                    $paid = floatval( $norm );
                    if ( $paid < 0 ) $paid = 0.0;
                    update_post_meta( $post_id, '_invoice_amount_paid', $paid );
                } else {
                    delete_post_meta( $post_id, '_invoice_amount_paid' );
                }
            }

            // invoice_status
            $status_raw = $get_field( 'invoice_status' );
            if ( $status_raw !== null ) {
                $status_s = sanitize_text_field( (string) $status_raw );
                $allowed_status = array( 'pending', 'paid', 'cancel', 'credit_note_issued' );
                $status = in_array( $status_s, $allowed_status, true ) ? $status_s : 'pending';
                update_post_meta( $post_id, '_invoice_status', $status );
            }

            // payment_date
            $pay_date = $get_field( 'payment_date' );
            if ( $pay_date !== null && $pay_date !== '' ) {
                update_post_meta( $post_id, '_payment_date', sanitize_text_field( (string) $pay_date ) );
            } else {
                delete_post_meta( $post_id, '_payment_date' );
            }

            // to_email
            $to_email = $get_field( 'to_email' );
            if ( $to_email !== null ) {
                update_post_meta( $post_id, '_to_email', sanitize_email( (string) $to_email ) );
            }

            // cc_emails -> array
            $cc_raw = $get_field( 'cc_emails' );
            if ( $cc_raw !== null ) {
                if ( is_array( $cc_raw ) ) {
                    $cc_arr = $cc_raw;
                } else {
                    $cc_arr = preg_split( '/[,\n\r;]+/', (string) $cc_raw );
                }
                $cc_emails = array_filter( array_map( 'sanitize_email', array_map( 'trim', (array) $cc_arr ) ) );
                update_post_meta( $post_id, '_cc_emails', $cc_emails );
            }

            // invoice_location / invoice_project / project_mode
            $loc = $get_field( 'invoice_location' );
            if ( $loc !== null ) {
                update_post_meta( $post_id, '_ims_location', sanitize_text_field( (string) $loc ) );
            }
            $proj = $get_field( 'invoice_project' );
            if ( $proj !== null ) {
                update_post_meta( $post_id, '_ims_project', sanitize_text_field( (string) $proj ) );
            }
            $mode_raw = $get_field( 'project_mode' );
            if ( $mode_raw !== null ) {
                $mode = sanitize_text_field( (string) $mode_raw );
                $allowed_modes = array( 'back_to_back', 'direct' );
                if ( in_array( $mode, $allowed_modes, true ) ) {
                    update_post_meta( $post_id, '_ims_project_mode', $mode );
                } else {
                    delete_post_meta( $post_id, '_ims_project_mode' );
                }
            }

            // -------------------------
            // Server-side derived calculations
            // -------------------------
            $basic_amount_for_calc = floatval( get_post_meta( $post_id, '_invoice_basic_amount', true ) ?: 0.0 );
            $gst_percent_for_calc  = intval( get_post_meta( $post_id, '_invoice_gst_percent', true ) ?: 0 );

            // GST amount
            $gst_amount_calc = round( ( $basic_amount_for_calc * $gst_percent_for_calc ) / 100, 2 );
            update_post_meta( $post_id, '_invoice_gst_amount', $gst_amount_calc );

            // Total amount = basic + gst
            $total_amount_calc = round( floatval( $basic_amount_for_calc ) + floatval( $gst_amount_calc ), 2 );
            update_post_meta( $post_id, '_invoice_total_amount', $total_amount_calc );

            // Total deduction: sum deduction keys
            $deduction_keys = array(
                '_invoice_retention_amount',
                '_invoice_gst_withheld',
                '_invoice_tds_amount',
                '_invoice_gst_tds_amount',
                '_invoice_bocw_amount',
                '_invoice_low_depth_deduction_amount',
                '_invoice_liquidated_damages_amount',
                '_invoice_sla_penalty_amount',
                '_invoice_penalty_amount',
                '_invoice_other_deduction_amount',
            );

            $total_deduction_calc = 0.0;
            foreach ( $deduction_keys as $k ) {
                $val = floatval( get_post_meta( $post_id, $k, true ) ?: 0 );
                $total_deduction_calc += $val;
            }
            $total_deduction_calc = round( $total_deduction_calc, 2 );
            update_post_meta( $post_id, '_invoice_total_deduction_amount', $total_deduction_calc );

            // Net payable = total - deductions
            $net_payable_calc = round( floatval( $total_amount_calc ) - floatval( $total_deduction_calc ), 2 );
            update_post_meta( $post_id, '_invoice_net_payable_amount', $net_payable_calc );

            // Balance = net - paid
            $amount_paid_stored = floatval( get_post_meta( $post_id, '_invoice_amount_paid', true ) ?: 0 );
            $balance_calc = round( floatval( $net_payable_calc ) - floatval( $amount_paid_stored ), 2 );
            update_post_meta( $post_id, '_invoice_balance_amount', $balance_calc );

            // Optional debug
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf(
                    'IMS: updated invoice %d — basic=%s, gst_pct=%d, gst=%s, total=%s, deduction=%s, net=%s, paid=%s, balance=%s',
                    $post_id,
                    number_format_i18n( $basic_amount_for_calc, 2 ),
                    $gst_percent_for_calc,
                    number_format_i18n( $gst_amount_calc, 2 ),
                    number_format_i18n( $total_amount_calc, 2 ),
                    number_format_i18n( $total_deduction_calc, 2 ),
                    number_format_i18n( $net_payable_calc, 2 ),
                    number_format_i18n( $amount_paid_stored, 2 ),
                    number_format_i18n( $balance_calc, 2 )
                ) );
            }

            return;
        } catch ( \Throwable $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( sprintf( 'IMS: elementor update error in %s on line %d: %s', $e->getFile(), $e->getLine(), $e->getMessage() ) );
            }
            return;
        }
    }
}
