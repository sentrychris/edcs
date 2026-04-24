<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GalnetNewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        preg_match('/\*(.*?)\*/', $this->content, $matches);
        $content = str_replace('<br />', '<br /><br />', $this->content);
        $content = strip_tags($content, '<div><p><br>');
        $content = count($matches) >= 1
            ? $this->partialReplace($content, '*', "<strong>$matches[1]</strong>")
            : $content;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $content,
            'audio_file' => $this->audio_file,
            'uploaded_at' => $this->uploaded_at,
            'banner_image' => $this->banner_image,
            'slug' => $this->slug,
        ];
    }

    private function partialReplace($str, $match, $replacement)
    {
        $pos = strpos($str, $match);
        $start = $pos === false ? 0 : $pos + strlen($match);

        $pos = strpos($str, $match, $start);
        $end = $pos === false ? strlen($str) : $pos;

        return substr_replace($str, $replacement, $start, $end - $start);
    }
}
