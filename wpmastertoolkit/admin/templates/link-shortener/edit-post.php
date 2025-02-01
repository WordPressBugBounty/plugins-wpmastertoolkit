<?php
global $post;
$title        = get_the_title( $post->ID );
$slug         = $post->post_name ?? '';
$redirect_url = get_post_meta( $post->ID, 'redirect_url', true );
$post_status  = get_post_status( $post->ID );
$permalink    = get_permalink( $post->ID );
$click_count  = (int) get_post_meta( $post->ID, $this->meta_click_count, true );

if ( ! $slug ) {
    $slug      = $this->generate_random_slug();
    $title     = '';
    $permalink = home_url( '/' . $this->default_slug . '/' . $slug );
}

$qr_code_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode( $permalink ) . '&format=svg';

?>
<hr><br>
<header class="wp-mastertoolkit__header">
    
    <div class="wp-mastertoolkit__header__center">
        <div class="wp-mastertoolkit__header__left__title">
            <input type="text" name="post_title" value="<?php echo esc_attr( $title ); ?>" placeholder="<?php esc_html_e( 'Enter a title', 'wpmastertoolkit' ); ?>" required>
        </div>
    </div>

    <div class="wp-mastertoolkit__header__right__save">
        <input type="hidden" name="post_status" value="publish">
        <?php submit_button( esc_html__('Save', 'wpmastertoolkit') ); ?>
    </div>
    
</header>


<div class="wp-mastertoolkit__section">
    <div class="wp-mastertoolkit__section__body">
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title activable">
                <div>
                    <?php esc_html_e("Status", 'wpmastertoolkit'); ?>
                </div>
                <div>
                    <label class="wp-mastertoolkit__toggle">
                        <input type="hidden" name="post_status" value="draft">
                        <input type="checkbox" name="post_status" value="publish" <?php checked( $post_status, 'publish' ); ?>>
                        <span class="wp-mastertoolkit__toggle__slider round"></span>
                    </label>
                </div>
            </div>
        </div>
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Shortened URL', 'wpmastertoolkit' ); ?></div>
            <div class="wp-mastertoolkit__section__body__item__content">
                <div class="wp-mastertoolkit__input-text slug-url">
                    <div>
                        <code>
                            <?php echo esc_url( home_url() . "/" . $this->default_slug . "/" ); ?>
                        </code>
                    </div>
                    <div>
                        <input type="hidden" class="home-url" value="<?php echo esc_url( home_url() . "/" . $this->default_slug . "/" ); ?>">
                        <input class="slug-input" type="text" name="<?php echo esc_attr( 'post_name' ); ?>" value="<?php echo esc_attr( $slug ); ?>" placeholder="<?php esc_html_e( 'Enter a custom slug', 'wpmastertoolkit' ); ?>" required>
                    </div>
                    <button class="copy-button">
                        <?php echo wp_kses( file_get_contents(WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/svg/copy.svg'), wpmastertoolkit_allowed_tags_for_svg_files() ); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <div class="wp-mastertoolkit__section__body__item">
            <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Redirect URL', 'wpmastertoolkit' ); ?></div>
            <div class="wp-mastertoolkit__section__body__item__content">
                <div class="wp-mastertoolkit__input-text">
                    <input type="url" name="<?php echo esc_attr( 'redirect_url' ); ?>" value="<?php echo esc_attr( $redirect_url ); ?>" placeholder="<?php esc_html_e( 'Enter URL', 'wpmastertoolkit' ); ?>" required>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="wp-mastertoolkit__sections-grid cols-3">
    <div class="wp-mastertoolkit__section">
        <div class="wp-mastertoolkit__section__body">
            <div class="wp-mastertoolkit__section__body__item">
                <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'Description', 'wpmastertoolkit' ); ?></div>
                <div class="wp-mastertoolkit__section__body__item__content">
                    <div class="wp-mastertoolkit__textarea">
                        <textarea name="post_excerpt" placeholder="<?php esc_html_e( 'Enter a description', 'wpmastertoolkit' ); ?>" style="height: 200px;"><?php echo esc_html( $post->post_excerpt ); ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="wp-mastertoolkit__section">
        <div class="wp-mastertoolkit__section__body click-count">
            <div class="click-counter-value">
                <?php echo number_format_i18n( $click_count ); ?>
            </div>
            <div class="click-counter-label">
                <?php echo _n( 'Click', 'Clicks', $click_count, 'wpmastertoolkit' ); ?>
            </div>
        </div>
    </div>
    <div class="wp-mastertoolkit__section">
        <div class="wp-mastertoolkit__section__body">
            <div class="wp-mastertoolkit__section__body__item">
                <div class="wp-mastertoolkit__section__body__item__title"><?php esc_html_e( 'QR code', 'wpmastertoolkit' ); ?></div>
                <div class="wp-mastertoolkit__section__body__item__content">
                    <div class="wp-mastertoolkit__qr-code">
                        <img src="<?php echo esc_url( $qr_code_url ); ?>" alt="<?php esc_attr_e( 'QR Code', 'wpmastertoolkit' ); ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>