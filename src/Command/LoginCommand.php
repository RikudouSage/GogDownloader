<?php

namespace App\Command;

use App\Service\AuthenticationManager;
use App\Service\WebAuthentication;
use App\Validator\NonEmptyValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('login')]
final class LoginCommand extends Command
{
    public function __construct(
        private readonly WebAuthentication $webAuthentication,
        private readonly AuthenticationManager $authenticationManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Login via email and password. May fail due to recaptcha, prefer code login.')
            ->addArgument(
                'email',
                InputArgument::OPTIONAL,
                'Email to log in as, if empty will be asked interactively',
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                "Your password. It's recommended to let the app ask for your password interactively instead of specifying it here."
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $this->getEmail($io, $input);
        $password = $this->getPassword($io, $input);

        $code = $this->webAuthentication->getCode($email, $password);
        if ($code === null) {
            $io->error('Failed to login using email and password. Either the credentials are wrong or there was a recaptcha.');

            return self::FAILURE;
        }

        $this->authenticationManager->codeLogin($code);
        $io->success('Successfully logged in');

        return self::SUCCESS;
    }

    private function getEmail(SymfonyStyle $io, InputInterface $input): string
    {
        if ($email = $input->getArgument('email')) {
            return $email;
        }

        return $io->ask('Email', validator: new NonEmptyValidator());
    }

    private function getPassword(SymfonyStyle $io, InputInterface $input): string
    {
        if ($password = $input->getOption('password')) {
            $io->warning("Don't forget to delete this command from your command history to avoid leaking your password!");

            return $password;
        }

        return $io->askHidden('Password', validator: new NonEmptyValidator());
    }
}
