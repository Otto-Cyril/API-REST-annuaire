<?php

namespace App\Command;

use App\Ldap\DirectoryCatalog;
use App\Ldap\DirectoryLookupInterface;
use App\Repository\PersonnelDeGardeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Relit l'AD pour chaque personnel de garde enregistré et met à jour le libellé « Prénom Nom », le service et le métier copiés en base,
 * puis vide le cache de l'annuaire du personnel pour qu'il se recharge depuis l'AD.
 * Un compte introuvable (supprimé ou renommé dans l'AD) est signalé mais jamais supprimé de la base.
 * À planifier (tâche planifiée Windows ou cron) pour que les changements de l'AD se retrouvent dans l'application.
 */
#[AsCommand(name: 'app:ldap:sync', description: "Recopie depuis l'AD le nom, le service et le métier du personnel de garde et rafraîchit l'annuaire du personnel")]
class LdapSyncCommand extends Command
{
    public function __construct(
        private readonly DirectoryLookupInterface $directory,
        private readonly DirectoryCatalog $catalog,
        private readonly PersonnelDeGardeRepository $personnelRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui changerait sans rien enregistrer');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $updated = 0;
        $missing = [];

        try {
            foreach ($this->personnelRepository->findAll() as $personnel) {
                $account = $this->directory->find((string) $personnel->getUsername());
                if (null === $account) {
                    $missing[] = $personnel->getUsername();
                    continue;
                }

                $libelle = mb_substr($account->fullName(), 0, 50);
                $service = $account->department ? mb_substr($account->department, 0, 150) : null;
                $metier = $account->title ? mb_substr($account->title, 0, 150) : null;
                if ([$libelle, $service, $metier] !== [$personnel->getLibelle(), $personnel->getService(), $personnel->getMetier()]) {
                    $io->writeln(sprintf('Personnel de garde %s : « %s » devient « %s » (%s, %s)', $account->username, $personnel->getLibelle(), $libelle, $service ?? 'sans service', $metier ?? 'sans poste'));
                    $personnel->setLibelle($libelle)->setService($service)->setMetier($metier);
                    ++$updated;
                }
            }
        } catch (HttpExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$dryRun) {
            $this->entityManager->flush();
            $this->catalog->refresh();
        }

        $io->success(sprintf('%d personnel(s) de garde %s.%s', $updated, $dryRun ? 'à mettre à jour (simulation)' : 'mis à jour', $dryRun ? '' : " Annuaire du personnel rafraîchi."));
        if ([] !== $missing) {
            $io->warning(array_merge(["Comptes introuvables dans l'AD (conservés en base) :"], $missing));
        }

        return Command::SUCCESS;
    }
}
