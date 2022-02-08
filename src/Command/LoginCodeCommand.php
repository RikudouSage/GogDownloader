<?php

namespace App\Command;

use App\Exception\AuthenticationException;
use App\Service\AuthenticationManager;
use App\Service\WebAuthentication;
use App\Validator\NonEmptyValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('code-login')]
final class LoginCodeCommand extends Command
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        return $this
            ->setDescription('Login using a code (for example when two-factor auth or recaptcha is required)')
            ->addArgument(
                'code',
                InputArgument::OPTIONAL,
                'The login code or url. Treat the code as you would treat a password. Providing the code as an argument is not recommended.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $io->writeln([
            '',
            sprintf('Visit %s and log in.', WebAuthentication::AUTH_URL),
            "After you're logged in you should be redirected to a blank page, copy the address of the page and paste it here (the prompt is invisible, you won't see that you pasted anything).",
        ]);

        if (!$code = $input->getArgument('code')) {
            $code = $io->askHidden('Code or web address', validator: new NonEmptyValidator());
        }
        $code = $this->getCode($code);
        $this->authenticationManager->codeLogin($code);

        $io->success('Logged in successfully.');

        return self::SUCCESS;
    }

    private function getCode(string $code): string
    {
        if (!str_starts_with($code, 'https://')) {
            return $code;
        }

        $query = parse_url($code, PHP_URL_QUERY);
        parse_str($query, $queryParams);

        if (!isset($queryParams['code'])) {
            throw new AuthenticationException('The URL does not contain a valid code.');
        }

        return $queryParams['code'];
    }
}
