<?php

namespace App\Console\Commands;

use App\Actions\Company\InstallCompany;
use App\Rules\CurrencyInUse;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\text;

#[Signature('hr:install
    {--company= : The company name}
    {--currency= : The company currency, as a three-letter code such as EUR}
    {--department= : The first Department}
    {--name= : Your full name}
    {--email= : Your email}')]
#[Description('Set up a brand-new installation of the HR app')]
class InstallCommand extends Command
{
    public function handle(InstallCompany $installer): int
    {
        if ($installer->isInstalled()) {
            $this->components->error('The HR app is already installed.');

            return self::FAILURE;
        }

        $input = [
            'company' => $this->valueOf('company', 'Company name'),
            'currency' => strtoupper($this->valueOf('currency', 'Company currency (three-letter code, e.g. EUR)')),
            'department' => $this->valueOf('department', 'First Department'),
            'name' => $this->valueOf('name', 'Your full name'),
            'email' => $this->valueOf('email', 'Your email'),
        ];

        $validator = Validator::make($input, [
            'company' => ['required', 'max:255'],
            'currency' => ['required', new CurrencyInUse],
            'department' => ['required', 'max:255'],
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $employee = $installer->install(...$input);

        $this->components->info("Installed. An invitation to set a password has been sent to {$employee->email}.");

        return self::SUCCESS;
    }

    private function valueOf(string $option, string $question): string
    {
        $value = $this->option($option);

        return is_string($value) && $value !== '' ? $value : text($question);
    }
}
