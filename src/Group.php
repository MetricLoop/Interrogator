<?php

namespace MetricLoop\Interrogator;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use MetricLoop\Interrogator\Exceptions\GroupNotFoundException;

class Group extends Model
{
    use SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'slug',
        'options',
        'section_id',
        'team_id',
    ];

    /**
     * The attributes that should be mutated as Dates.
     *
     * @var array
     */
    protected $dates = [
        'deleted_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'options' => 'array',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'order'
    ];

    /**
     * Creates a new instance of the model.
     *
     * @param array $attributes
     */
    public function __construct( array $attributes = [ ] )
    {
        parent::__construct( $attributes );
        $this->table = 'groups';
    }

    /**
     * Returns the Section to which this Group belongs.
     *
     * @return BelongsTo
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * Get all the Questions that belong to this Group.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function questions()
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Delete Questions before deleting Group itself.
     *
     * @return bool|null
     * @throws \Exception
     */
    public function delete()
    {
        $this->questions->each(function ($question) {
            $question->delete();
        });
        return parent::delete();
    }

    /**
     * Restores Group and Questions with matching "deleted_at" timestamps.
     */
    public function restore()
    {
        $deleted_at = $this->deleted_at;

        $this->questions()->withTrashed()->get()->filter(function ($question) use ($deleted_at) {
            $first = $second = $deleted_at;
            return $question->deleted_at->gte($first) && $question->deleted_at->lte($second->addSecond());
        })->each(function ($question) {
            $question->restore();
        });
        parent::restore();
    }

    /**
     * Set an Option (brand new or updating existing).
     *
     * @param $key
     * @param $value
     * @return $this
     */
    public function setOption($key, $value)
    {
        $options = $this->options;
        $options[$key] = $value;
        $this->options = $options;
        $this->save();
        return $this;
    }

    /**
     * Unset an Option.
     *
     * @param $key
     * @return $this
     */
    public function unsetOption($key)
    {
        $options = $this->options;
        unset($options[$key]);
        $this->options = $options;
        $this->save();
        return $this;
    }

    /**
     * Sync list of Options.
     *
     * @param $options
     * @return $this
     */
    public function syncOptions($options)
    {
        $currentOptions = is_array($this->options) ? $this->options : [];
        $optionsToRemove = array_diff_key($currentOptions, $options);
        foreach($options as $key => $value) {
            $this->setOption($key, $value);
        }
        foreach($optionsToRemove as $key => $value) {
            $this->unsetOption($key);
        }

        return $this;
    }

    /**
     * Accessor for attribute.
     *
     * @return int
     */
    public function getOrderAttribute()
    {
        return isset($this->options['order']) ? $this->options['order'] : 1;
    }

    /**
     * Resolves Group object regardless of given identifier.
     *
     * @param $group
     * @param bool $withTrashed
     * @return null
     * @throws GroupNotFoundException
     */
    public static function resolveSelf($group, $withTrashed = false)
    {
        if(is_null($group)) { return null; }

        if(!$group instanceof Group) {
            if(is_numeric($group)) {
                try {
                    if($withTrashed) {
                        $group = Group::withTrashed()->findOrFail($group);
                    } else {
                        $group = Group::findOrFail($group);
                    }
                } catch (ModelNotFoundException $e) {
                    throw new GroupNotFoundException('Group not found with the given ID.');
                }
            } else {
                try {
                    if($withTrashed) {
                        $group = Group::withTrashed()->whereSlug($group)->firstOrFail();
                    } else {
                        $group = Group::whereSlug($group)->firstOrFail();
                    }
                } catch (ModelNotFoundException $e) {
                    throw new GroupNotFoundException('Group not found with the given slug.');
                }
            }
        }
        return $group;
    }
}