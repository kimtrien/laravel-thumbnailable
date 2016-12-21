<?php
namespace Kjmtrue\Thumbnailable;

use Config;
use File;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Intervention\Image\ImageManagerStatic as Image;

trait Thumbnailable
{
//    protected $thumbnailable = [
//        'storage_dir'     => 'public/demo',
//        'storage_slug_by' => 'name',
//        'fields'          => [
//            'image' => [
//                'default_size' => 'S',
//                'sizes'        => [
//                    'S' => '50x50',
//                    'M' => '100x100',
//                    'L' => '200x200',
//                ]
//            ]
//        ],
//    ];

    public static function bootThumbnailable()
    {
        static::creating(function (Model $item) {
            $item->upload_file();
        });

        static::deleting(function (Model $item) {
            $item->delete_file();
        });

        static::updating(function (Model $item) {
            $item->update_file();
        });
    }

    /**
     * @param $field_name
     * @param null $size
     * @return string
     */
    public function thumb($field_name, $size = null)
    {
        $filename      = $this->getAttribute($field_name);

        $original_name = pathinfo($filename, PATHINFO_FILENAME);
        $extension     = pathinfo($filename, PATHINFO_EXTENSION);

        if ($size) {
            $filename = $original_name . '_' . $size . '.' . $extension;
        }

        return $this->getPublicUrl() . '/' . $filename;
    }

    public function rethumb($field_name)
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['fields']) && isset($this->thumbnailable['fields'][$field_name])) {
            $field_value = $this->thumbnailable['fields'][$field_name];

            $filename = $this->getAttribute($field_name);
            $sizes = $field_value['sizes'];

            if (file_exists($this->getStorageDir() . DIRECTORY_SEPARATOR . $filename)) {
                $this->saveThumb($filename, $sizes);
            }
        }
    }

    protected function upload_file()
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['fields'])) {
            foreach ($this->thumbnailable['fields'] as $field_name => $field_value) {
                $sizes = $field_value['sizes'];

                $file = $this->getAttribute($field_name);

                if ($file instanceof UploadedFile) {
                    $filename = $this->saveFile($file);
                    $this->setAttribute($field_name, $filename);

                    $this->saveThumb($filename, $sizes);
                }
            }
        }
    }

    protected function update_file()
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['fields'])) {
            foreach ($this->thumbnailable['fields'] as $field_name => $field_value) {
                $sizes = $field_value['sizes'];

                $file = $this->getAttribute($field_name);

                if ($file instanceof UploadedFile) {
                    $filename = $this->saveFile($file);
                    $this->setAttribute($field_name, $filename);

                    $this->saveThumb($filename, $sizes);

                    $old_filename = $this->getOriginal($field_name);
                    $this->clean_field($old_filename, $sizes);
                }
            }
        }
    }

    protected function delete_file()
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['fields'])) {
            foreach ($this->thumbnailable['fields'] as $field_name => $field_value) {
                $sizes = $field_value['sizes'];

                $filename = $this->getAttribute($field_name);

                $this->clean_field($filename, $sizes);
            }
        }
    }

    protected function clean_field($filename, $sizes)
    {
        $original_name = pathinfo($filename, PATHINFO_FILENAME);
        $extension     = pathinfo($filename, PATHINFO_EXTENSION);

        $original_file = $this->getStorageDir() . DIRECTORY_SEPARATOR . $filename;
        File::delete($original_file);

        foreach ($sizes as $size_code => $size) {
            $thumb_name = $this->getStorageDir() . DIRECTORY_SEPARATOR . $original_name . '_' . $size_code . '.' . $extension;

            File::delete($thumb_name);
        }
    }

    protected function saveFile(UploadedFile $file)
    {
        $filename = $this->checkFileName($file->getClientOriginalName());

        if ($file->isValid()) {
            $file->move($this->getStorageDir(), $filename);

            return $filename;
        }

        return '';
    }

    protected function saveThumb($filename, $sizes)
    {
        $original_name = pathinfo($filename, PATHINFO_FILENAME);
        $extension     = pathinfo($filename, PATHINFO_EXTENSION);
        $full_file     = $this->getStorageDir() . DIRECTORY_SEPARATOR . $filename;

        // Image::configure(array('driver' => 'imagick'));
        // $image = Image::make($full_file);

        foreach ($sizes as $size_code => $size) {
            $thumb_name = $this->getStorageDir() . DIRECTORY_SEPARATOR . $original_name . '_' . $size_code . '.' . $extension;
            $wh = explode('x', $size);
            $width = $wh[0];
            $height = $wh[1];

            try {
                $image = Image::make($full_file);
                $image->fit($width, $height, function ($constraint) {
                    $constraint->upsize();
                })->save($thumb_name, $this->getQuality());
            } catch (\Exception $e) {
                echo "Error {$full_file}";
            }
        }
    }

    protected function checkFileName($filename)
    {
        $filedir  = $this->getStorageDir();

        $actual_name   = str_slug(pathinfo($filename, PATHINFO_FILENAME));
        $original_name = $actual_name;
        $extension     = pathinfo($filename, PATHINFO_EXTENSION);

        $filename = $actual_name . "." . $extension;

        $i = 1;
        while(file_exists($filedir . DIRECTORY_SEPARATOR . $actual_name . "." . $extension))
        {
            $actual_name = (string) $original_name . '-' . $i;
            $filename    = $actual_name . "." . $extension;
            $i++;
        }

        return $filename;
    }

    protected function getStorageDir()
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['storage_dir'])) {
            if (isset($this->thumbnailable['storage_slug_by'])) {
                $slug = str_slug($this->getAttribute($this->thumbnailable['storage_slug_by']));

                return $this->thumbnailable['storage_dir'] . DIRECTORY_SEPARATOR . $slug;
            } else {
                return $this->thumbnailable['storage_dir'];
            }
        }

        return Config::get('thumbnailable.storage_dir', storage_path('images'));
    }

    protected function getQuality()
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['quality'])) {
            return $this->thumbnailable['quality'];
        }

        return Config::get('thumbnailable.quality', 100);
    }

    protected function getPublicUrl()
    {
        if (isset($this->thumbnailable) && isset($this->thumbnailable['storage_dir'])) {
            if (isset($this->thumbnailable['storage_slug_by'])) {
                $slug = str_slug($this->getAttribute($this->thumbnailable['storage_slug_by']));

                return $this->thumbnailable['storage_dir'] . '/' . $slug;
            } else {
                return $this->thumbnailable['storage_dir'];
            }
        }

        return Config::get('thumbnailable.storage_dir', 'storage/images');
    }
}