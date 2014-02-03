<?php
namespace Hostnet\Component\EntityPlugin;

use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

/**
 * The generator for stage 2 that only has to hook into composer
 * It generates the combined entity and repository traits
 * Generated/ClientTrait and Generated/ClientRepositoryTrait
 *
 * @author Nico Schoenmaker <nschoenmaker@hostnet.nl>
 */
class CompoundGenerator
{

    private $io;

    private $environment;

    private $entity_package;

    public function __construct(IOInterface $io, \Twig_Environment $environment, EntityPackage $entity_package)
    {
        $this->io             = $io;
        $this->environment    = $environment;
        $this->entity_package = $entity_package;
    }

    /**
     * Ask the generator to generate all the trait of traits, and their matching combined interfaces
     *
     * @return void
     */
    public function generate()
    {
        foreach ($this->entity_package->getPackageIO()->getEntities() as $package_class) {
            /* @var $package_class PackageClass */
            $traits = $this->recursivelyFindUseStatementsFor($this->entity_package, $package_class);
            $this->generateTrait($package_class, $traits);
            $this->generateInterface($package_class, $traits);
        }
    }

    /**
     * Gives all the entities to be required in the compound interface
     * Also generates a unique alias for them
     *
     * @param EntityPackage $entity_package
     * @param string $class_name
     * @return array[]UseStatement
     */
    private function recursivelyFindUseStatementsFor(EntityPackage $entity_package, PackageClass $package_class)
    {
        $result = array();
        foreach ($entity_package->getDependentPackages() as $dependent_package) {
            /* @var $package EntityPackage */
            $result = array_merge($result, $this->recursivelyFindUseStatementsFor($dependent_package, $package_class));
        }
        $package_class = $entity_package->getPackageIO()->getEntityOrEntityTrait($package_class->getShortName());
        if ($package_class) {
            $result[] = new UseStatement($package_class->getNamespaceName(), $package_class);
        }
        return $result;
    }

    /**
     * Generates Generated/<class_name>Traits.php
     *
     * @param string $relative_path
     *            The relative path to the directory to generate the trait in
     * @param string $class_name
     * @param array $traits
     */
    private function generateTrait(PackageClass $package_class, array $traits)
    {
        $short_name = $package_class->getShortName();

        $this->writeIfVeryVerbose(
            '        - Generating trait of traits for <info>' . $package_class->getName() . '</info>'
        );

        $generated_namespace = $package_class->getGeneratedNamespaceName();

        $data = $this->environment->render(
            'traits.php.twig',
            array(
                'class_name' => $short_name,
                'namespace' => $generated_namespace,
                'use_statements' => $traits
            )
        );

        $this->entity_package->getPackageIO()->writeGeneratedFile(
            $package_class->getGeneratedDirectory(),
            $short_name . 'Traits.php',
            $data
        );
    }

    /**
     * Generates Generated/<class_name>Interfaces.php
     *
     * @param string $relative_path
     *            The relative path to the directory to generate the interface in
     * @param string $class_name
     * @param array $traits
     */
    private function generateInterface(PackageClass $package_class, array $traits)
    {
        $short_name = $package_class->getShortName();

        $this->writeIfVeryVerbose(
            '        - Generating combined interface for <info>' . $package_class->getName() . '</info>'
        );

        $generated_namespace = $package_class->getGeneratedNamespaceName();

        $data = $this->environment->render(
            'combined_interface.php.twig',
            array(
                'class_name' => $short_name,
                'namespace' => $generated_namespace,
                'use_statements' => $traits
            )
        );

        $this->entity_package->getPackageIO()->writeGeneratedFile(
            $package_class->getGeneratedDirectory(),
            $short_name . 'Interface.php',
            $data
        );
    }

    /**
     *
     * @todo these lines are also in the installer
     * @param string $path
     * @return string Namespace
     */
    private function convertPathToNamespace($path)
    {
        return str_replace('/', '\\', $path);
    }

    private function writeIfVeryVerbose($text)
    {
        if ($this->io->isVeryVerbose()) {
            $this->io->write($text);
        }
    }
}