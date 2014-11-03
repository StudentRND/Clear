<?php
namespace CodeDay\Clear\Models;

class User extends \Eloquent {
    protected $table = 'users';
    protected $primaryKey = 'username';
    public $incrementing = false;

    public function getNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function check_group($group)
    {
        if ($this->is_admin) {
            return true;
        }

        foreach ($this->groups as $m_group) {
            if ($group == $m_group->id) {
                return true;
            }
        }

        return false;
    }

    public function check_groups($groups)
    {
        foreach ($groups as $group) {
            if ($this->check_group($group)) {
                return true;
            }
        }

        return false;
    }

    public function forget()
    {
        \Session::forget('s5_username');
    }

    public static function me()
    {
        if (!\Session::has('s5_username')) {
            self::api()->RequireLogin(['extended']);
            \Session::set('s5_username', self::api()->User->me()->username);
        }

        return self::fromS5Username(\Session::get('s5_username'));
    }

    public static function fromS5Username($username)
    {
        $user = self::find($username);
        if (!$user) {
            $user = new self;
        }

        if (!$user->exists || $user->updated_at->diffInDays() > 1) {
            try {
                $s5_user = self::api()->User->get($username);
            } catch (\Exception $ex) {
                return null;
            }

            $user->username = $s5_user->username;
            $user->first_name = $s5_user->first_name;
            $user->last_name = $s5_user->last_name;
            $user->email = $s5_user->email;
            $user->phone = $s5_user->phone;
            $user->is_admin = $s5_user->is_admin;
            $user->save();
        }

        return $user;
    }

    public function managedEvents()
    {
        return $this->hasMany('\CodeDay\Clear\Models\Batch\Event', 'manager_username', 'username');
    }

    public function getCurrentManagedEventsAttribute()
    {
        return Batch\Event::select('batches_events.*')
                        ->where('batch_id', '=', Batch::Loaded()->id)
                        ->join('users_grants', 'users_grants.batches_event_id', '=', 'batches_events.id', 'left')
                        ->where(function($query) {
                            $query->where('users_grants.username', '=', $this->username)
                                ->orWhere('batches_events.manager_username', '=', $this->username)
                                ->orWhere('batches_events.evangelist_username', '=', $this->username);
                        })
                        ->groupBy('batches_events.id')
                        ->get();
    }

    private static $_s5 = null;
    private static function api()
    {
        if (!isset(self::$_s5)) {
            self::$_s5 = new \s5\API(\Config::get('s5.token'), \Config::get('s5.secret'));
        }

        return self::$_s5;
    }
}