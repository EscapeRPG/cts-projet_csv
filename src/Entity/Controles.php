<?php

namespace App\Entity;

use App\Repository\ControlesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ControlesRepository::class)]
#[ORM\Table(
    uniqueConstraints: [
        new ORM\UniqueConstraint(columns: ['idcontrole'])
    ]
)]
class Controles
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idcontrole = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExport = null;

    #[ORM\Column(length: 20)]
    private ?string $numPvCtrl = null;

    #[ORM\Column(length: 20)]
    private ?string $numLiaCtrl = null;

    #[ORM\Column(length: 12)]
    private ?string $immatVehicule = null;

    #[ORM\Column(length: 12)]
    private ?string $numSerieVehicule = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePriseRdv = null;

    #[ORM\Column(length: 1)]
    private ?string $typeRdv = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $debCtrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $finCtrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCtrl = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $tempsCtrl = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $refTemps = null;

    #[ORM\Column(length: 2)]
    private ?string $resCtrl = null;

    #[ORM\Column(length: 5)]
    private ?string $typeCtrl = null;

    #[ORM\Column(length: 255)]
    private ?string $modeleVehicule = null;

    #[ORM\Column]
    private ?int $anneeCirculation = null;

    /**
     * @var Collection<int, PrestasNonFacturees>
     */
    #[ORM\OneToMany(targetEntity: PrestasNonFacturees::class, mappedBy: 'idcontrole')]
    private Collection $prestasNonFacturees;

    /**
     * @var Collection<int, ControlesNonFactures>
     */
    #[ORM\OneToMany(targetEntity: ControlesNonFactures::class, mappedBy: 'idcontrole')]
    private Collection $controlesNonFactures;

    /**
     * @var Collection<int, ClientsControles>
     */
    #[ORM\OneToMany(targetEntity: ClientsControles::class, mappedBy: 'idcontrole')]
    private Collection $clientsControles;

    /**
     * @var Collection<int, ControlesFactures>
     */
    #[ORM\OneToMany(targetEntity: ControlesFactures::class, mappedBy: 'idcontrole')]
    private Collection $controlesFactures;

    public function __construct()
    {
        $this->prestasNonFacturees = new ArrayCollection();
        $this->controlesNonFactures = new ArrayCollection();
        $this->clientsControles = new ArrayCollection();
        $this->controlesFactures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdcontrole(): ?string
    {
        return $this->idcontrole;
    }

    public function setIdcontrole(string $idcontrole): static
    {
        $this->idcontrole = $idcontrole;

        return $this;
    }

    public function getDateExport(): ?\DateTimeImmutable
    {
        return $this->dateExport;
    }

    public function setDateExport(\DateTimeImmutable $dateExport): static
    {
        $this->dateExport = $dateExport;

        return $this;
    }

    public function getNumPvCtrl(): ?string
    {
        return $this->numPvCtrl;
    }

    public function setNumPvCtrl(string $numPvCtrl): static
    {
        $this->numPvCtrl = $numPvCtrl;

        return $this;
    }

    public function getNumLiaCtrl(): ?string
    {
        return $this->numLiaCtrl;
    }

    public function setNumLiaCtrl(string $numLiaCtrl): static
    {
        $this->numLiaCtrl = $numLiaCtrl;

        return $this;
    }

    public function getImmatVehicule(): ?string
    {
        return $this->immatVehicule;
    }

    public function setImmatVehicule(string $immatVehicule): static
    {
        $this->immatVehicule = $immatVehicule;

        return $this;
    }

    public function getNumSerieVehicule(): ?string
    {
        return $this->numSerieVehicule;
    }

    public function setNumSerieVehicule(string $numSerieVehicule): static
    {
        $this->numSerieVehicule = $numSerieVehicule;

        return $this;
    }

    public function getDatePriseRdv(): ?\DateTimeImmutable
    {
        return $this->datePriseRdv;
    }

    public function setDatePriseRdv(?\DateTimeImmutable $datePriseRdv): static
    {
        $this->datePriseRdv = $datePriseRdv;

        return $this;
    }

    public function getTypeRdv(): ?string
    {
        return $this->typeRdv;
    }

    public function setTypeRdv(string $typeRdv): static
    {
        $this->typeRdv = $typeRdv;

        return $this;
    }

    public function getDebCtrl(): ?\DateTimeImmutable
    {
        return $this->debCtrl;
    }

    public function setDebCtrl(\DateTimeImmutable $debCtrl): static
    {
        $this->debCtrl = $debCtrl;

        return $this;
    }

    public function getFinCtrl(): ?\DateTimeImmutable
    {
        return $this->finCtrl;
    }

    public function setFinCtrl(\DateTimeImmutable $finCtrl): static
    {
        $this->finCtrl = $finCtrl;

        return $this;
    }

    public function getDateCtrl(): ?\DateTimeImmutable
    {
        return $this->dateCtrl;
    }

    public function setDateCtrl(\DateTimeImmutable $dateCtrl): static
    {
        $this->dateCtrl = $dateCtrl;

        return $this;
    }

    public function getTempsCtrl(): ?int
    {
        return $this->tempsCtrl;
    }

    public function setTempsCtrl(int $tempsCtrl): static
    {
        $this->tempsCtrl = $tempsCtrl;

        return $this;
    }

    public function getRefTemps(): ?int
    {
        return $this->refTemps;
    }

    public function setRefTemps(int $refTemps): static
    {
        $this->refTemps = $refTemps;

        return $this;
    }

    public function getResCtrl(): ?string
    {
        return $this->resCtrl;
    }

    public function setResCtrl(string $resCtrl): static
    {
        $this->resCtrl = $resCtrl;

        return $this;
    }

    public function getTypeCtrl(): ?string
    {
        return $this->typeCtrl;
    }

    public function setTypeCtrl(string $typeCtrl): static
    {
        $this->typeCtrl = $typeCtrl;

        return $this;
    }

    public function getModeleVehicule(): ?string
    {
        return $this->modeleVehicule;
    }

    public function setModeleVehicule(string $modeleVehicule): static
    {
        $this->modeleVehicule = $modeleVehicule;

        return $this;
    }

    public function getAnneeCirculation(): ?int
    {
        return $this->anneeCirculation;
    }

    public function setAnneeCirculation(int $anneeCirculation): static
    {
        $this->anneeCirculation = $anneeCirculation;

        return $this;
    }

    /**
     * @return Collection<int, PrestasNonFacturees>
     */
    public function getPrestasNonFacturees(): Collection
    {
        return $this->prestasNonFacturees;
    }

    public function addPrestasNonFacturee(PrestasNonFacturees $prestasNonFacturee): static
    {
        if (!$this->prestasNonFacturees->contains($prestasNonFacturee)) {
            $this->prestasNonFacturees->add($prestasNonFacturee);
            $prestasNonFacturee->setIdcontrole($this);
        }

        return $this;
    }

    public function removePrestasNonFacturee(PrestasNonFacturees $prestasNonFacturee): static
    {
        if ($this->prestasNonFacturees->removeElement($prestasNonFacturee)) {
            // set the owning side to null (unless already changed)
            if ($prestasNonFacturee->getIdcontrole() === $this) {
                $prestasNonFacturee->setIdcontrole(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ControlesNonFactures>
     */
    public function getControlesNonFactures(): Collection
    {
        return $this->controlesNonFactures;
    }

    public function addControlesNonFacture(ControlesNonFactures $controlesNonFacture): static
    {
        if (!$this->controlesNonFactures->contains($controlesNonFacture)) {
            $this->controlesNonFactures->add($controlesNonFacture);
            $controlesNonFacture->setIdcontrole($this);
        }

        return $this;
    }

    public function removeControlesNonFacture(ControlesNonFactures $controlesNonFacture): static
    {
        if ($this->controlesNonFactures->removeElement($controlesNonFacture)) {
            // set the owning side to null (unless already changed)
            if ($controlesNonFacture->getIdcontrole() === $this) {
                $controlesNonFacture->setIdcontrole(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ClientsControles>
     */
    public function getClientsControles(): Collection
    {
        return $this->clientsControles;
    }

    public function addClientsControle(ClientsControles $clientsControle): static
    {
        if (!$this->clientsControles->contains($clientsControle)) {
            $this->clientsControles->add($clientsControle);
            $clientsControle->setIdcontrole($this);
        }

        return $this;
    }

    public function removeClientsControle(ClientsControles $clientsControle): static
    {
        if ($this->clientsControles->removeElement($clientsControle)) {
            // set the owning side to null (unless already changed)
            if ($clientsControle->getIdcontrole() === $this) {
                $clientsControle->setIdcontrole(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ControlesFactures>
     */
    public function getControlesFactures(): Collection
    {
        return $this->controlesFactures;
    }

    public function addControlesFacture(ControlesFactures $controlesFacture): static
    {
        if (!$this->controlesFactures->contains($controlesFacture)) {
            $this->controlesFactures->add($controlesFacture);
            $controlesFacture->setIdcontrole($this);
        }

        return $this;
    }

    public function removeControlesFacture(ControlesFactures $controlesFacture): static
    {
        if ($this->controlesFactures->removeElement($controlesFacture)) {
            // set the owning side to null (unless already changed)
            if ($controlesFacture->getIdcontrole() === $this) {
                $controlesFacture->setIdcontrole(null);
            }
        }

        return $this;
    }
}
