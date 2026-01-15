<?php

namespace App\Entity;

use App\Repository\ClientsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ClientsRepository::class)]
#[ORM\Table(
    uniqueConstraints: [
        new ORM\UniqueConstraint(columns: ['idclient'])
    ]
)]
class Clients
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::BIGINT)]
    private ?string $idclient = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateExport = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $codeClient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nomCodeClient = null;

    #[ORM\Column(length: 25, nullable: true)]
    private ?string $codeSage = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse2 = null;

    #[ORM\Column(length: 5)]
    private ?string $cp = null;

    #[ORM\Column(length: 255)]
    private ?string $ville = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $numTvaIntra = null;

    /**
     * @var Collection<int, CentresClients>
     */
    #[ORM\OneToMany(targetEntity: CentresClients::class, mappedBy: 'idclient')]
    private Collection $centresClients;

    /**
     * @var Collection<int, ControlesNonFactures>
     */
    #[ORM\OneToMany(targetEntity: ControlesNonFactures::class, mappedBy: 'idclient')]
    private Collection $controlesNonFactures;

    /**
     * @var Collection<int, ClientsControles>
     */
    #[ORM\OneToMany(targetEntity: ClientsControles::class, mappedBy: 'idclient')]
    private Collection $clientsControles;

    /**
     * @var Collection<int, ControlesFactures>
     */
    #[ORM\OneToMany(targetEntity: ControlesFactures::class, mappedBy: 'idclient')]
    private Collection $controlesFactures;

    /**
     * @var Collection<int, FacturesReglements>
     */
    #[ORM\OneToMany(targetEntity: FacturesReglements::class, mappedBy: 'idclient')]
    private Collection $facturesReglements;

    public function __construct()
    {
        $this->centresClients = new ArrayCollection();
        $this->controlesNonFactures = new ArrayCollection();
        $this->clientsControles = new ArrayCollection();
        $this->controlesFactures = new ArrayCollection();
        $this->facturesReglements = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setIdclient(string $idclient): self
    {
        $this->idclient = $idclient;
        return $this;
    }

    public function getIdclient(): ?string
    {
        return $this->idclient;
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

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getCodeClient(): ?string
    {
        return $this->codeClient;
    }

    public function setCodeClient(string $codeClient): static
    {
        $this->codeClient = $codeClient;

        return $this;
    }

    public function getNomCodeClient(): ?string
    {
        return $this->nomCodeClient;
    }

    public function setNomCodeClient(?string $nomCodeClient): static
    {
        $this->nomCodeClient = $nomCodeClient;

        return $this;
    }

    public function getCodeSage(): ?string
    {
        return $this->codeSage;
    }

    public function setCodeSage(?string $codeSage): static
    {
        $this->codeSage = $codeSage;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getAdresse1(): ?string
    {
        return $this->adresse1;
    }

    public function setAdresse1(?string $adresse1): static
    {
        $this->adresse1 = $adresse1;

        return $this;
    }

    public function getAdresse2(): ?string
    {
        return $this->adresse2;
    }

    public function setAdresse2(?string $adresse2): static
    {
        $this->adresse2 = $adresse2;

        return $this;
    }

    public function getCp(): ?string
    {
        return $this->cp;
    }

    public function setCp(string $cp): static
    {
        $this->cp = $cp;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getMobile(): ?string
    {
        return $this->mobile;
    }

    public function setMobile(?string $mobile): static
    {
        $this->mobile = $mobile;

        return $this;
    }

    public function getNumTvaIntra(): ?string
    {
        return $this->numTvaIntra;
    }

    public function setNumTvaIntra(?string $numTvaIntra): static
    {
        $this->numTvaIntra = $numTvaIntra;

        return $this;
    }

    /**
     * @return Collection<int, CentresClients>
     */
    public function getCentresClients(): Collection
    {
        return $this->centresClients;
    }

    public function addCentresClient(CentresClients $centresClient): static
    {
        if (!$this->centresClients->contains($centresClient)) {
            $this->centresClients->add($centresClient);
            $centresClient->setIdclient($this);
        }

        return $this;
    }

    public function removeCentresClient(CentresClients $centresClient): static
    {
        if ($this->centresClients->removeElement($centresClient)) {
            // set the owning side to null (unless already changed)
            if ($centresClient->getIdclient() === $this) {
                $centresClient->setIdclient(null);
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
            $controlesNonFacture->setIdclient($this);
        }

        return $this;
    }

    public function removeControlesNonFacture(ControlesNonFactures $controlesNonFacture): static
    {
        if ($this->controlesNonFactures->removeElement($controlesNonFacture)) {
            // set the owning side to null (unless already changed)
            if ($controlesNonFacture->getIdclient() === $this) {
                $controlesNonFacture->setIdclient(null);
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
            $clientsControle->setIdclient($this);
        }

        return $this;
    }

    public function removeClientsControle(ClientsControles $clientsControle): static
    {
        if ($this->clientsControles->removeElement($clientsControle)) {
            // set the owning side to null (unless already changed)
            if ($clientsControle->getIdclient() === $this) {
                $clientsControle->setIdclient(null);
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
            $controlesFacture->setIdclient($this);
        }

        return $this;
    }

    public function removeControlesFacture(ControlesFactures $controlesFacture): static
    {
        if ($this->controlesFactures->removeElement($controlesFacture)) {
            // set the owning side to null (unless already changed)
            if ($controlesFacture->getIdclient() === $this) {
                $controlesFacture->setIdclient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, FacturesReglements>
     */
    public function getFacturesReglements(): Collection
    {
        return $this->facturesReglements;
    }

    public function addFacturesReglement(FacturesReglements $facturesReglement): static
    {
        if (!$this->facturesReglements->contains($facturesReglement)) {
            $this->facturesReglements->add($facturesReglement);
            $facturesReglement->setIdclient($this);
        }

        return $this;
    }

    public function removeFacturesReglement(FacturesReglements $facturesReglement): static
    {
        if ($this->facturesReglements->removeElement($facturesReglement)) {
            // set the owning side to null (unless already changed)
            if ($facturesReglement->getIdclient() === $this) {
                $facturesReglement->setIdclient(null);
            }
        }

        return $this;
    }
}
