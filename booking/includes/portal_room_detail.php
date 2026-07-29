<?php
/**
 * Rich room-type detail markup for the Select a Room modal (Hilton-style two-column layout).
 */

if (!function_exists('hb_portal_render_amenities_scroll')) {
    require_once __DIR__ . '/portal_chrome.php';
}

if (!function_exists('hb_portal_room_detail_categorize_bullets')) {
    function hb_portal_room_detail_categorize_bullets(array $bullets) {
        $layout = [];
        $bathroom = [];
        $kitchen = [];
        $comfort = [];
        foreach ($bullets as $b) {
            $low = strtolower($b);
            if (preg_match('/shower|bath|bidet|toilet/i', $low)) {
                $bathroom[] = $b;
            } elseif (preg_match('/minibar|kitchen|nespresso|coffee|dining/i', $low)) {
                $kitchen[] = $b;
            } elseif (preg_match('/balcony|workspace|desk|view|layout/i', $low)) {
                $layout[] = $b;
            } else {
                $comfort[] = $b;
            }
        }
        return ['layout' => $layout, 'bathroom' => $bathroom, 'kitchen' => $kitchen, 'comfort' => $comfort];
    }
}

if (!function_exists('hb_portal_room_detail_modal_html')) {
    function hb_portal_room_detail_modal_html(array $card, array $hotelAmenities, $currencyCode, $bookUrl, $available) {
        $name = (string) ($card['type_name'] ?? '');
        $bed = (string) ($card['bed_summary'] ?? '');
        $title = $name;
        if ($bed !== '' && stripos($name, $bed) === false) {
            $title = trim($name . ' — ' . $bed);
        }
        $img = (string) ($card['image_url'] ?? '');
        $desc = (string) ($card['type_description'] ?? '');
        $size = $card['type_size_sqm'] ?? '';
        $view = (string) ($card['view_label'] ?? '');
        $maxAdults = (int) ($card['max_adults'] ?? 2);
        $maxChildren = (int) ($card['max_children'] ?? 0);
        $bullets = is_array($card['bullets'] ?? null) ? $card['bullets'] : [];
        $cats = hb_portal_room_detail_categorize_bullets($bullets);
        $quoted = (float) ($card['quoted_price'] ?? 0);
        $priceLabel = hb_portal_money_format($quoted, $currencyCode);

        $specParts = [];
        if ($size !== '' && $size !== null) {
            $specParts[] = (string) $size . ' m²';
        }
        if ($view !== '') {
            $specParts[] = strtolower($view) . ' view';
        }
        foreach (array_slice($bullets, 0, 4) as $b) {
            $specParts[] = $b;
        }
        $specLine = implode(', ', array_unique($specParts));

        $occLabel = 'Max. occupancy: ' . $maxAdults . ' adult' . ($maxAdults === 1 ? '' : 's');
        if ($maxChildren > 0) {
            $occLabel .= ', ' . $maxChildren . ' child' . ($maxChildren === 1 ? '' : 'ren');
        }

        $amenityHtml = '';
        ob_start();
        hb_portal_render_amenities_scroll($hotelAmenities, 10);
        $amenityHtml = (string) ob_get_clean();

        $highlight = function ($titleText, $items) {
            if (empty($items)) {
                return '';
            }
            $lis = '';
            foreach ($items as $it) {
                $lis .= '<li>' . htmlspecialchars($it, ENT_QUOTES, 'UTF-8') . '</li>';
            }
            return '<div class="hb-rd-highlight-col"><h4>' . htmlspecialchars($titleText, ENT_QUOTES, 'UTF-8') . '</h4><ul>' . $lis . '</ul></div>';
        };

        $guestItems = ['Sleeps ' . $maxAdults];
        if ($maxChildren > 0) {
            $guestItems[] = 'Children welcome';
        }

        $layoutItems = $cats['layout'];
        if ($view !== '' && empty($layoutItems)) {
            $layoutItems[] = ucfirst($view) . ' view';
        }

        $highlightsHtml =
            $highlight('Guests', $guestItems) .
            $highlight('Room layout', $layoutItems) .
            $highlight('Bathroom', $cats['bathroom']) .
            $highlight('Kitchen and dining', $cats['kitchen']);

        $comfortHtml = '';
        foreach ($cats['comfort'] as $c) {
            $comfortHtml .= '<li>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
        }
        if ($comfortHtml === '' && !empty($bullets)) {
            foreach ($bullets as $c) {
                $comfortHtml .= '<li>' . htmlspecialchars($c, ENT_QUOTES, 'UTF-8') . '</li>';
            }
        }

        $bookClass = 'hb-btn hb-btn-primary hb-room-detail-book';
        if (!$available) {
            $bookClass .= ' is-disabled';
        }

        ob_start();
        ?>
<div class="hb-room-detail-layout" data-type-id="<?php echo (int) ($card['type_id'] ?? 0); ?>">
<div class="hb-room-detail-left">
<h2 class="hb-rd-title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h2>
<div class="hb-rd-hero" style="background-image:url('<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>')"></div>
<p class="hb-rd-occ"><?php echo htmlspecialchars($occLabel, ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($specLine !== ''): ?>
<p class="hb-rd-spec"><?php echo htmlspecialchars($specLine, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>
<?php if ($desc !== ''): ?>
<p class="hb-rd-desc hb-rd-desc-short"><?php echo htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></p>
<button type="button" class="hb-rd-read-more" data-hb-read-more title="Read more">Read more</button>
<?php endif; ?>
<?php if ($available): ?>
<a class="<?php echo htmlspecialchars($bookClass, ENT_QUOTES, 'UTF-8'); ?>" href="<?php echo htmlspecialchars($bookUrl, ENT_QUOTES, 'UTF-8'); ?>" title="Book this room">Book From <?php echo htmlspecialchars($priceLabel, ENT_QUOTES, 'UTF-8'); ?></a>
<?php else: ?>
<button type="button" class="hb-btn hb-btn-disabled hb-room-detail-book" disabled title="Not available">Not available</button>
<?php endif; ?>
</div>
<div class="hb-room-detail-right">
<section class="hb-rd-section">
<h3>Hotel amenities</h3>
<?php echo $amenityHtml; ?>
</section>
<section class="hb-rd-section">
<h3>Room highlights</h3>
<div class="hb-rd-highlights"><?php echo $highlightsHtml; ?></div>
</section>
<details class="hb-rd-more" open>
<summary>More room details</summary>
<div class="hb-rd-more-body">
<h4>For your comfort</h4>
<ul><?php echo $comfortHtml; ?></ul>
</div>
</details>
</div>
</div>
        <?php
        return (string) ob_get_clean();
    }
}
