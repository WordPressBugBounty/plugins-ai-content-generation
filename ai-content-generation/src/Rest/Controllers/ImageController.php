<?php

namespace WPWand\Rest\Controllers;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Image generation (DALL·E) + saving to the Media Library.
 *
 * Self-sufficient port of the legacy wpwand_dall_e_request / wpwand_insert_media: reads the
 * OpenAI key from options directly (no constant/init dependency) and returns structured
 * JSON (base64 data URLs) instead of HTML.
 *
 * - POST /wpwand/v1/generate/image  → { images: [dataUrl...], resolution }
 * - POST /wpwand/v1/media           → { id, url }   (save a generated image)
 */
final class ImageController extends AbstractController
{
    public function register_routes(): void
    {
        register_rest_route(
            $this->rest_namespace,
            '/generate/image',
            [
                ['methods' => 'POST', 'callback' => [$this, 'generate'], 'permission_callback' => [$this, 'can_use']],
            ]
        );

        register_rest_route(
            $this->rest_namespace,
            '/media',
            [
                ['methods' => 'POST', 'callback' => [$this, 'save'], 'permission_callback' => [$this, 'can_use']],
            ]
        );
    }

    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $api_key = (string) get_option('wpwand_api_key', '');
        if ($api_key === '') {
            return new WP_REST_Response(['error' => __('An OpenAI API key is required for image generation.', 'ai-content-generation')], 400);
        }

        $prompt = sanitize_text_field((string) $request->get_param('prompt'));
        if ($prompt === '') {
            return new WP_REST_Response(['error' => __('Please describe the image.', 'ai-content-generation')], 400);
        }

        $resolution = sanitize_text_field((string) $request->get_param('resolution')) ?: '256x256';
        $number     = max(1, min(3, absint($request->get_param('number') ?: 1)));

        // The panel greys out 512/1024 without Pro, but the route has to hold the line too — the
        // picker is not the only thing that can POST here. Clamp instead of rejecting, so a stale
        // bundle or a saved value still gets an image back rather than an error.
        $sizes = \WPWand\Core\Pro::unlocked() ? ['256x256', '512x512', '1024x1024'] : ['256x256'];
        if (!in_array($resolution, $sizes, true)) {
            $resolution = '256x256';
        }

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                // Same knob as text generation, so a slow host is tuned in one place.
                'timeout' => \WPWand\Generation\Generator::http_timeout(),
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => wp_json_encode([
                    // Pinned on purpose: 256x256, n up to 3 and b64_json are all dall-e-2 terms.
                    // Leaving the model out means riding OpenAI's default, and the newer image
                    // model rejects response_format outright.
                    'model'           => 'dall-e-2',
                    'prompt'          => $prompt,
                    'n'               => $number,
                    'size'            => $resolution,
                    'response_format' => 'b64_json',
                ]),
            ]
        );

        if (is_wp_error($response)) {
            // Transport failure — never reached OpenAI, so this is a raw cURL string until the
            // formatter turns it into something the user can act on.
            $msg = \WPWand\Generation\ErrorFormatter::humanize(
                $response->get_error_message(),
                __('Image generation failed.', 'ai-content-generation'),
                'openai'
            );
            return new WP_REST_Response(['error' => $msg], 200);
        }

        $body = json_decode(wp_remote_retrieve_body($response));

        if (isset($body->error)) {
            $msg = \WPWand\Generation\ErrorFormatter::humanize($body->error, __('Image generation failed.', 'ai-content-generation'), 'openai');
            return new WP_REST_Response(['error' => $msg], 200);
        }

        $images = [];
        if (isset($body->data) && is_array($body->data)) {
            foreach ($body->data as $img) {
                if (isset($img->b64_json)) {
                    $images[] = 'data:image/png;base64,' . $img->b64_json;
                }
            }
        }

        if (empty($images)) {
            return new WP_REST_Response(['error' => __('No image returned. Please try again.', 'ai-content-generation')], 200);
        }

        return new WP_REST_Response(['images' => $images, 'resolution' => $resolution, 'prompt' => $prompt], 200);
    }

    public function save(WP_REST_Request $request): WP_REST_Response
    {
        $data = (string) $request->get_param('image');
        $name = sanitize_file_name((string) $request->get_param('name') ?: 'wpwand-image');

        if ($data === '') {
            return new WP_REST_Response(['error' => __('No image data.', 'ai-content-generation')], 400);
        }

        // Accept a data URL or a bare base64 string. Strict mode matters: the lenient default
        // silently drops invalid characters, so "not-base64" decoded to a few bytes, passed the
        // empty() guard, and was written into the media library as a 6-byte .png.
        if (strpos($data, 'data:image') === 0) {
            $parts  = explode(',', $data);
            $binary = base64_decode((string) end($parts), true);
        } else {
            $binary = base64_decode($data, true);
        }

        if (!is_string($binary) || $binary === '') {
            return new WP_REST_Response(['error' => __('That image data could not be read. Try generating the image again.', 'ai-content-generation')], 400);
        }

        // Decoding cleanly is not the same as being an image. Take the extension from what the bytes
        // actually are rather than assuming PNG.
        $info = @getimagesizefromstring($binary);
        if ($info === false || empty($info['mime'])) {
            return new WP_REST_Response(['error' => __("That doesn't look like an image, so nothing was saved.", 'ai-content-generation')], 400);
        }

        $ext = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ][$info['mime']] ?? null;

        if ($ext === null) {
            return new WP_REST_Response(['error' => __('That image type is not supported.', 'ai-content-generation')], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $upload = wp_upload_bits($name . '.' . $ext, null, $binary);
        if (!empty($upload['error'])) {
            return new WP_REST_Response(['error' => $upload['error']], 200);
        }

        $filetype   = wp_check_filetype($upload['file'], null);
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title'     => $name,
            'post_content'   => '',
            'post_status'    => 'inherit',
            // Without this the attachment's guid is the attachment-page permalink, not the file.
            'guid'           => $upload['url'],
        ];

        $attach_id = wp_insert_attachment($attachment, $upload['file']);
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $upload['file']));

        return new WP_REST_Response(['id' => $attach_id, 'url' => wp_get_attachment_url($attach_id)], 200);
    }
}
