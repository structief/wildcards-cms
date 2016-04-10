<?php

namespace Cockpit\Controller;

class Assets extends \Cockpit\AuthController {

    public function index() {

        return $this->render('cockpit:views/assets/index.php');
    }

    public function listAssets() {

        $options = [
            'sort' => ['created' => -1]
        ];

        if ($filter = $this->param("filter", null)) $options["filter"] = $filter;
        if ($limit  = $this->param("limit", null))  $options["limit"] = $limit;
        if ($sort   = $this->param("sort", null))   $options["sort"] = $sort;
        if ($skip   = $this->param("skip", null))   $options["skip"] = $skip;

        $assets = $this->storage->find("cockpit/assets", $options);

        $this->app->trigger('cockpit.assets.list', [&$assets]);

        return $assets;
    }

    public function upload() {

        $files      = isset($_FILES['files']) ? $_FILES['files'] : [];
        $path       = $this->app->path('#uploads:');
        $targetpath = $path.'/'.date('Y/m/d');
        $uploaded   = [];
        $failed     = [];

        // absolute paths for hook
        $_uploaded  = [];
        $_failed    = [];
        $assets     = [];

        if (!file_exists($targetpath) && !mkdir($targetpath, 0777, true)) {
            return false;
        }

        if (isset($files['name']) && $path && file_exists($targetpath)) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            for ($i = 0; $i < count($files['name']); $i++) {

                // clean filename
                $clean    = uniqid().preg_replace('/[^a-zA-Z0-9-_\.]/','', str_replace(' ', '-', $files['name'][$i]));
                $target   = $targetpath.'/'.$clean;

                if (!$files['error'][$i] && move_uploaded_file($files['tmp_name'][$i], $target)) {

                    $created = time();

                    $asset = [
                        'path' => str_replace($path, '', $target),
                        'name' => $files['name'][$i],
                        'mime' => finfo_file($finfo, $target),
                        'description' => '',
                        'tags' => [],
                        'size' => filesize($target),
                        'image' => preg_match('/\.(jpg|jpeg|png|gif|svg)$/i', $target) ? true:false,
                        'video' => preg_match('/\.(mp4|mov|ogv|webv|wmv|flv|avi)$/i', $target) ? true:false,
                        'audio' => preg_match('/\.(mp3|weba|ogg|wav|flac)$/i', $target) ? true:false,
                        'archive' => preg_match('/\.(zip|rar|7zip|gz|tar)$/i', $target) ? true:false,
                        'document' => preg_match('/\.(txt|htm|html|pdf|md)$/i', $target) ? true:false,
                        'code' => preg_match('/\.(htm|html|php|css|less|js|json|md|markdown|yaml|xml|htaccess)$/i', $target) ? true:false,
                        'created' => $created,
                        'modified' => $created
                    ];

                    if ($asset['image'] && !preg_match('/\.svg$/i', $target)) {
                        $info = getimagesize($target);
                        $asset['width']  = $info[0];
                        $asset['height'] = $info[1];
                    }

                    $assets[] = $asset;

                    $uploaded[]  = $files['name'][$i];
                    $_uploaded[] = $targetpath.'/'.$clean;

                } else {
                    $failed[]    = $files['name'][$i];
                    $_failed[]   = $targetpath.'/'.$clean;
                }
            }

            finfo_close($finfo);
        }

        if (count($assets)) {
            $this->app->trigger('cockpit.assets.upload', [$assets]);
            $this->app->storage->insert("cockpit/assets", $assets);
        }

        return json_encode(['uploaded' => $uploaded, 'failed' => $failed, 'assets' => $assets]);
    }

    public function removeAssets() {


        if ($assets = $this->param('assets', false)) {

            foreach($assets as $asset) {

                if (!isset($asset['_id'])) continue;

                $this->app->storage->remove("cockpit/assets", ['_id' => $asset['_id']]);

                if (isset($asset['path']) && $file = $this->app->path('#uploads:'.$asset['path'])) {
                    unlink($file);
                }
            }

            return $assets;
        }

        return false;

    }

    public function updateAsset() {

        if ($asset = $this->param('asset', false)) {

            $asset['modified'] = time();

            $this->app->storage->save("cockpit/assets", $asset);

            return $asset;
        }

        return false;
    }

}
