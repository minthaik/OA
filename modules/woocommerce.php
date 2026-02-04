<?php
if (!defined('ABSPATH')) exit;
class OA_WooCommerce {
  public static function init(){
    add_action('woocommerce_thankyou',[__CLASS__,'track_purchase'],10,1);
    add_action('woocommerce_payment_complete',[__CLASS__,'track_purchase'],10,1);
  }
  public static function track_purchase($order_id){
    if(!$order_id) return;
    $order=wc_get_order($order_id);
    if(!$order) return;
    if($order->get_status()==='failed') return;
    if(method_exists($order,'is_paid') && !$order->is_paid()) return;
    if($order->get_meta('_oa_analytics_recorded')) return;

    $total=(float)$order->get_total();
    $day=OA_Util::today_ymd();
    global $wpdb; $pfx=$wpdb->prefix.'oa_';

    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$pfx}daily_revenue (day,orders,revenue)
       VALUES (%s,1,%f)
       ON DUPLICATE KEY UPDATE orders=orders+1, revenue=revenue+%f",
      $day,$total,$total
    ));

    $coupon_codes=method_exists($order,'get_coupon_codes') ? (array)$order->get_coupon_codes() : [];
    $coupon_codes=array_values(array_filter(array_map(function($code){ return sanitize_text_field((string)$code); }, $coupon_codes)));
    if (!empty($coupon_codes)){
      $discount_total=(float)$order->get_discount_total();
      $split=max(1,count($coupon_codes));
      $discount_share=$discount_total/$split;
      $revenue_share=$total/$split;
      foreach($coupon_codes as $code){
        if ($code==='') continue;
        $coupon_key=strtolower($code);
        $ch=OA_Util::hash_key($coupon_key);
        $wpdb->query($wpdb->prepare(
          "INSERT INTO {$pfx}daily_coupons (day,coupon_hash,coupon_code,orders,discount_total,revenue_total)
           VALUES (%s,%s,%s,1,%f,%f)
           ON DUPLICATE KEY UPDATE orders=orders+1, discount_total=discount_total+%f, revenue_total=revenue_total+%f",
          $day,$ch,$coupon_key,$discount_share,$revenue_share,$discount_share,$revenue_share
        ));
      }
    }

    // record aggregated event "purchase"
    $eh=OA_Util::hash_key('purchase');
    $meta='currency='.get_woocommerce_currency();
    $mh=OA_Util::hash_key($meta);
    $wpdb->query($wpdb->prepare(
      "INSERT INTO {$pfx}daily_events (day,event_hash,event_name,meta_hash,meta,count)
       VALUES (%s,%s,%s,%s,%s,1)
       ON DUPLICATE KEY UPDATE count=count+1",
      $day,$eh,'purchase',$mh,$meta
    ));

    // if a goal exists that matches purchase, count it (value uses order total)
    $goals=$wpdb->get_results("SELECT id,value FROM {$pfx}goals WHERE is_enabled=1 AND ((type='event' AND match_value='purchase') OR type='purchase')", ARRAY_A);
    foreach($goals as $g){
      $gid=(int)$g['id'];
      $use= $total>0 ? $total : (float)$g['value'];
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$pfx}daily_goals (day,goal_id,hits,value_sum)
         VALUES (%s,%d,1,%f)
         ON DUPLICATE KEY UPDATE hits=hits+1, value_sum=value_sum+%f",
        $day,$gid,$use,$use
      ));
    }

    $order->update_meta_data('_oa_analytics_recorded', current_time('mysql'));
    $order->save();
  }
}
