<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class SupabaseStorageService
{
    protected ?string $supabaseUrl;
    protected ?string $serviceRoleKey;

    public function __construct()
    {
        $this->supabaseUrl = config('services.supabase.url') ?: env('SUPABASE_URL');
        $this->serviceRoleKey = config('services.supabase.service_role_key') ?: env('SUPABASE_SERVICE_ROLE_KEY');
    }

    /**
     * Upload an uploaded file or file contents to Supabase Storage bucket.
     * Falls back to local public storage if Supabase credentials are not configured.
     */
    public function uploadFile(UploadedFile|string $file, string $bucket = 'product-images', string $folder = 'uploads'): string
    {
        $filename = uniqid() . '_' . time() . ($file instanceof UploadedFile ? '.' . $file->getClientOriginalExtension() : '.png');
        $storagePath = trim($folder, '/') . '/' . $filename;

        if ($this->supabaseUrl && $this->serviceRoleKey) {
            $contents = $file instanceof UploadedFile ? file_get_contents($file->getRealPath()) : $file;
            $mimeType = $file instanceof UploadedFile ? $file->getMimeType() : 'image/png';

            $endpoint = rtrim($this->supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $storagePath;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'apikey' => $this->serviceRoleKey,
                'Content-Type' => $mimeType,
                'x-upsert' => 'true',
            ])->withBody($contents, $mimeType)->post($endpoint);

            if ($response->successful()) {
                return rtrim($this->supabaseUrl, '/') . '/storage/v1/object/public/' . $bucket . '/' . $storagePath;
            }
        }

        // Fallback to standard Laravel public storage if Supabase credentials are unset
        if ($file instanceof UploadedFile) {
            $path = $file->storeAs('public/' . $folder, $filename);
            return asset('storage/' . str_replace('public/', '', $path));
        }

        Storage::disk('public')->put($folder . '/' . $filename, $file);
        return asset('storage/' . $folder . '/' . $filename);
    }

    /**
     * Delete a file from Supabase Storage or local storage.
     */
    public function deleteFile(string $url, string $bucket = 'product-images'): bool
    {
        if (empty($url)) return false;

        if ($this->supabaseUrl && $this->serviceRoleKey && str_contains($url, '/storage/v1/object/public/')) {
            $parsedPath = parse_url($url, PHP_URL_PATH);
            $objectPath = str_replace('/storage/v1/object/public/' . $bucket . '/', '', $parsedPath);

            $endpoint = rtrim($this->supabaseUrl, '/') . '/storage/v1/object/' . $bucket . '/' . $objectPath;

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->serviceRoleKey,
                'apikey' => $this->serviceRoleKey,
            ])->delete($endpoint);

            return $response->successful();
        }

        return true;
    }
}
