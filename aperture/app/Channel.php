<?php

namespace App;

use App\Events\SourceRemoved;
use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $fillable = [
        'name',
        'icon',
        'sparkline',
        'read_tracking_mode',
        'include_only',
        'include_keywords',
        'exclude_types',
        'exclude_keywords',
    ];

    public function user()
    {
        return $this->belongsTo('\App\User');
    }

    public function sources()
    {
        return $this->belongsToMany('\App\Source')
            ->withPivot(['name', 'site_url', 'fetch_original', 'xpath_selector']);
    }

    public function entries()
    {
        return $this->belongsToMany('\App\Entry')
            ->withPivot(['seen', 'original_data']);
    }

    public function excluded_types()
    {
        if ($this->exclude_types) {
            return explode(' ', $this->exclude_types);
        }

        return [];
    }

    public function to_array()
    {
        $array = [
            'uid' => $this->uid,
            'name' => $this->name,
        ];

        switch ($this->read_tracking_mode) {
            case 'count':
                $array['unread'] = $this->entries()
                    ->wherePivot('seen', 0)
                    ->count();
                break;

            case 'boolean':
                $array['unread'] = $this->entries()
                    ->wherePivot('seen', 0)
                    ->count() > 0;
                break;
        }

        if ($this->default_destination) {
            $array['destination'] = $this->default_destination;
        }

        return $array;
    }

    public function remove_source(Source $source, $remove_entries = false)
    {
        if ($remove_entries) {
            $entryIds = $this->entries()
                ->where('entries.source_id', $source->id)
                ->pluck('entries.id')
                ->all();

            $this->entries()
                ->detach($entryIds);
        }

        $this->sources()
            ->detach($source->id);

        event(new SourceRemoved($source, $this));
    }

    public function delete()
    {
        foreach ($this->sources as $source) {
            $this->remove_source($source);
        }

        parent::delete();
    }

    public function remove_entries(array $entryIds)
    {
        return $this->entries()
            ->detach($entryIds);
    }

    public function mark_entries_read(array $entryIds)
    {
        return $this->entries()
            ->updateExistingPivot($entryIds, ['seen' => 1]);
    }

    public function mark_entries_unread(array $entryIds)
    {
        return $this->entries()
            ->updateExistingPivot($entryIds, ['seen' => 0]);
    }

    public function mark_entries_read_before(Entry $entry)
    {
        return $this->entries()
            ->where('entries.published', '<', $entry->published)
            ->orWhere(function ($query) use ($after) {
                $query->where('entries.published', '=', $entry->published)
                    ->where('entries.id', '<=', $entry->id);
            })
            ->update(['seen' => 1]);
    }

    public function should_add_entry(Entry $entry)
    {
        $shouldAdd = true;

        // If the channel has type or keyword filters, check them now before adding
        if ($this->include_only) {
            switch ($this->include_only) {
                case 'photos_videos':
                    // allow any post with a photo, not just photo posts. e.g. a checkin with a photo
                    $shouldAdd = in_array($entry->post_type(), ['photo', 'video'], true) || $entry->has_photo();
                    break;
                case 'articles':
                    $shouldAdd = $entry->post_type() === 'article';
                    break;
                case 'checkins':
                    $shouldAdd = $entry->post_type() === 'checkin';
                    break;
                case 'reposts':
                    $shouldAdd = $entry->post_type() === 'repost';
                    break;
            }
        }

        // at least one keyword must match to have the post included,
        // but don't let keyword whitelist override the type filter
        if ($shouldAdd && $this->include_keywords) {
            $shouldAdd = false;
            $keywords = explode(' ', $this->include_keywords);

            foreach ($keywords as $kw) {
                if ($entry->matches_keyword($kw)) {
                    $shouldAdd = true;
                    break;
                }
            }
        }

        if ($this->exclude_types) {
            // if the post is any one of the excluded types, reject it now
            foreach ($this->excluded_types() as $type) {
                if ($entry->post_type() == $type) {
                    $shouldAdd = false;
                    break;
                }
            }
        }

        if ($this->exclude_keywords) {
            // if the post matches any of the blacklisted terms, reject it now
            $keywords = explode(' ', $this->exclude_keywords);

            foreach ($keywords as $kw) {
                if ($entry->matches_keyword($kw)) {
                    $shouldAdd = false;
                    break;
                }
            }
        }

        return $shouldAdd;
    }
}
