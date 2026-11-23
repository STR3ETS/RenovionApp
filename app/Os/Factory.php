<?php

namespace App\Os;

use App\Models\OsRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * De fabrieksstaat: de volledige vorm van het systeem zonder demodata
 * (database/os/factory.json, gemaakt met database/os/build-factory.cjs uit
 * het prototype). Bij installeren komen de bestaande interne accounts erin
 * als medewerkers, zodat iedereen meteen kan inloggen en ingepland kan worden.
 */
final class Factory
{
    public static function state(): object
    {
        return json_decode(file_get_contents(database_path('os/factory.json')), false, flags: JSON_THROW_ON_ERROR);
    }

    public function install(): void
    {
        $state = self::state();
        $state->emps = [];

        $users = User::query()->where('active', true)->orderBy('id')->get()->reject->isExternal()->values();
        $taken = [];
        foreach ($users as $user) {
            $employee = $this->employee($user, $taken);
            $taken[] = $employee->id;
            $state->emps[] = $employee;
        }

        $rows = Layout::toRows($state, 0);
        $now = now();

        DB::transaction(function () use ($rows, $now, $users, $state) {
            DB::table('os_meta')->insertOrIgnore(['id' => 1, 'rev' => 0]);
            $rev = (int) DB::table('os_meta')->where('id', 1)->lockForUpdate()->value('rev') + 1;
            DB::table('os_meta')->where('id', 1)->update(['rev' => $rev]);

            $insert = [];
            foreach ($rows as $path => $value) {
                if (str_starts_with($path, '~')) {
                    continue;
                }
                $insert[] = ['path' => $path, 'collection' => Layout::collectionOf($path), 'value' => $value, 'rev' => $rev, 'created_at' => $now, 'updated_at' => $now];
            }
            foreach (array_chunk($insert, 200) as $chunk) {
                OsRow::query()->insert($chunk);
            }

            DB::table('os_changes')->insert(['rev' => $rev, 'user_id' => null, 'paths' => json_encode(['#fabrieksstaat']), 'created_at' => $now]);

            foreach ($users as $i => $user) {
                $user->forceFill(['person_id' => $state->emps[$i]->id])->save();
            }
        });
    }

    /** Een medewerker in de vorm van het systeem, gemaakt van een account. */
    /** @param  list<string>  $taken */
    private function employee(User $user, array $taken): object
    {
        $label = $user->role->label();
        $office = ! in_array($label, ['Uitvoerder'], true);
        $id = $user->person_id ?: $this->slug($user, $taken);

        return (object) [
            'id' => $id,
            'naam' => $user->name,
            'rol' => $office ? $label : 'Uitvoerder',
            'type' => $office ? 'kantoor' : 'intern',
            'cap' => 40,
            'regio' => 'Arnhem',
            'tarief' => $office ? 0 : 55,
            'talen' => ['NL'],
            'certs' => [],
            'rating' => 5,
            'sinds' => $user->created_at?->toDateString() ?? now()->toDateString(),
            'specs' => [$office ? $label : 'Uitvoerder'],
            'tel' => (string) ($user->phone ?? ''),
            'mail' => $user->email,
            'afwezig' => [],
            'sysrol' => $label,
            'status' => 'actief',
            'notif' => (object) ['mail' => true, 'push' => false, 'dag' => true],
            'scope' => 'alle',
        ];
    }

    /** @param  list<string>  $taken */
    private function slug(User $user, array $taken): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii(explode(' ', trim($user->name))[0]))) ?: 'medewerker';
        $id = $base;
        for ($i = 2; in_array($id, $taken, true); $i++) {
            $id = $base.$i;
        }

        return $id;
    }
}
