<?php

namespace duncan3dc\Sonos;

use duncan3dc\Sonos\Devices\Device;
use duncan3dc\Sonos\Interfaces\Devices\DeviceInterface;
use duncan3dc\Sonos\Interfaces\SpeakerInterface;

/**
 * Represents an individual Sonos speaker, to allow volume, equalisation, and other settings to be managed.
 */
final class Speaker implements SpeakerInterface
{
    /**
     * @var string $ip The IP address of the speaker.
     */
    private $ip;

    /**
     * @var DeviceInterface $device The instance of the Device class to send requests to.
     */
    private $device;

    /**
     * @var string $name The "Friendly" name reported by the speaker.
     */
    private $name;

    /**
     * @var string $room The room name assigned to this speaker.
     */
    private $room;

    /**
     * @var string $uuid The unique id of this speaker.
     */
    private $uuid;

    /**
     * @var string $group The group id this speaker is a part of.
     */
    private $group;

    /**
     * @var bool $coordinator Whether this speaker is the coordinator of it's current group.
     */
    private $coordinator;

    /**
     * @var bool $topology A flag to indicate whether we have gathered the topology for this speaker or not.
     */
    private $topology;


    /**
     * Create an instance of the Speaker class.
     *
     * @param DeviceInterface|string $param An Device instance or the ip address that the speaker is listening on
     */
    public function __construct($param)
    {
        if ($param instanceof DeviceInterface) {
            $this->device = $param;
            $this->ip = $this->device->getIp();
        } else {
            $this->ip = $param;
            $this->device = new Device($this->ip);
        }

        $parser = $this->device->getXml("/xml/device_description.xml");
        $device = $parser->getTag("device");
        $this->name = (string) $device->getTag("friendlyName");
        $this->room = (string) $device->getTag("roomName");

        if (!$this->device->isSpeaker()) {
            throw new \InvalidArgumentException("You cannot create a Speaker instance for this model: " . $this->device->getModel());
        }
    }


    /**
     * Send a soap request to the speaker.
     *
     * @param string $service The service to send the request to
     * @param string $action The action to call
     * @param array $params The parameters to pass
     *
     * @return mixed
     */
    public function soap(string $service, string $action, array $params = [])
    {
        return $this->device->soap($service, $action, $params);
    }


    /**
     * Remove any previously gathered topology for this speaker.
     *
     * @return $this
     */
    public function clearTopology(): SpeakerInterface
    {
        $this->topology = false;

        return $this;
    }


    /**
     * Set the topology of this speaker.
     *
     * @param array $topology The topology attributes as key/value pairs
     *
     * @return $this
     */
    public function setTopology(array $topology): SpeakerInterface
    {
        $this->topology = true;

        $this->group = $topology["group"];
        $this->coordinator = ($topology["coordinator"] === "true");
        $this->uuid = $topology["uuid"];

        return $this;
    }


    /**
     * Get the attributes needed for the classes instance variables.
     *
     * @return void
     */
    private function getTopology()
    {
        if ($this->topology) {
            return;
        }

        $topology = $this->device->getXml("/status/topology");
        $players = $topology->getTag("ZonePlayers")->getTags("ZonePlayer");
        foreach ($players as $player) {
            $attributes = $player->getAttributes();
            $ip = parse_url($attributes["location"])["host"];

            if ($ip === $this->ip) {
                $this->setTopology($attributes);
                return;
            }
        }

        throw new \RuntimeException("Failed to lookup the topology info for this speaker");
    }


    /**
     * Get the IP address of this speaker.
     *
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }


    /**
     * Get the "Friendly" name of this speaker.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }


    /**
     * Get the room name of this speaker.
     *
     * @return string
     */
    public function getRoom(): string
    {
        return $this->room;
    }


    /**
     * Get the uuid of the group this speaker is a member of.
     *
     * @return string
     */
    public function getGroup(): string
    {
        $this->getTopology();
        return $this->group;
    }


    /**
     * Check if this speaker is the coordinator of it's current group.
     *
     * @return bool
     */
    public function isCoordinator(): bool
    {
        $this->getTopology();
        return $this->coordinator;
    }


    /**
     * Get the uuid of this speaker.
     *
     * @return string The uuid of this speaker
     */
    public function getUuid(): string
    {
        $this->getTopology();
        return $this->uuid;
    }


    /**
     * Get the current volume of this speaker.
     *
     * @param int The current volume between 0 and 100
     *
     * @return int
     */
    public function getVolume(): int
    {
        return (int) $this->soap("RenderingControl", "GetVolume", [
            "Channel"   =>  "Master",
        ]);
    }


    /**
     * Adjust the volume of this speaker to a specific value.
     *
     * @param int $volume The amount to set the volume to between 0 and 100
     *
     * @return $this
     */
    public function setVolume(int $volume): SpeakerInterface
    {
        $this->soap("RenderingControl", "SetVolume", [
            "Channel"       =>  "Master",
            "DesiredVolume" =>  $volume,
        ]);

        return $this;
    }


    /**
     * Adjust the volume of this speaker by a relative amount.
     *
     * @param int $adjust The amount to adjust by between -100 and 100
     *
     * @return $this
     */
    public function adjustVolume(int $adjust): SpeakerInterface
    {
        $this->soap("RenderingControl", "SetRelativeVolume", [
            "Channel"       =>  "Master",
            "Adjustment"    =>  $adjust,
        ]);

        return $this;
    }


    /**
     * Check if this speaker is currently muted.
     *
     * @return bool
     */
    public function isMuted(): bool
    {
        return (bool) $this->soap("RenderingControl", "GetMute", [
            "Channel"   =>  "Master",
        ]);
    }


    /**
     * Mute this speaker.
     *
     * @param bool $mute Whether the speaker should be muted or not
     *
     * @return $this
     */
    public function mute(bool $mute = true): SpeakerInterface
    {
        $this->soap("RenderingControl", "SetMute", [
            "Channel"       =>  "Master",
            "DesiredMute"   =>  $mute ? 1 : 0,
        ]);

        return $this;
    }


    /**
     * Unmute this speaker.
     *
     * @return $this
     */
    public function unmute(): SpeakerInterface
    {
        return $this->mute(false);
    }


    /**
     * Turn the indicator light on or off.
     *
     * @param bool $on Whether the indicator should be on or off
     *
     * @return $this
     */
    public function setIndicator(bool $on): SpeakerInterface
    {
        $this->soap("DeviceProperties", "SetLEDState", [
            "DesiredLEDState"   =>  $on ? "On" : "Off",
        ]);

        return $this;
    }


    /**
     * Check whether the indicator light is on or not.
     *
     * @return bool
     */
    public function getIndicator(): bool
    {
        return ($this->soap("DeviceProperties", "GetLEDState") === "On");
    }


    /**
     * Set the bass/treble equalisation level.
     *
     * @param string $type Which setting to update (bass or treble)
     * @param int $value The value to set (between -10 and 10)
     *
     * @return $this
     */
    private function setEqLevel(string $type, int $value): SpeakerInterface
    {
        if ($value < -10) {
            $value = -10;
        }
        if ($value > 10) {
            $value = 10;
        }

        $type = ucfirst(strtolower($type));
        $this->soap("RenderingControl", "Set{$type}", [
            "Channel"           =>  "Master",
            "Desired{$type}"    =>  $value,
        ]);

        return $this;
    }

    /**
     * Get the treble equalisation level.
     *
     * @return int
     */
    public function getTreble(): int
    {
        return (int) $this->soap("RenderingControl", "GetTreble", [
            "Channel"           =>  "Master",
        ]);
    }


    /**
     * Set the treble equalisation.
     *
     * @param int $treble The treble level (between -10 and 10)
     *
     * @return $this
     */
    public function setTreble(int $treble): SpeakerInterface
    {
        return $this->setEqLevel("treble", $treble);
    }


    /**
     * Get the bass equalisation level.
     *
     * @return int
     */
    public function getBass(): int
    {
        return (int) $this->soap("RenderingControl", "GetBass", [
            "Channel"           =>  "Master",
        ]);
    }


    /**
     * Set the bass equalisation.
     *
     * @param int $bass The bass level (between -10 and 10)
     *
     * @return $this
     */
    public function setBass(int $bass): SpeakerInterface
    {
        return $this->setEqLevel("bass", $bass);
    }


    /**
     * Check whether loudness normalisation is on or not.
     *
     * @return bool
     */
    public function getLoudness(): bool
    {
        return (bool) $this->soap("RenderingControl", "GetLoudness", [
            "Channel"       =>  "Master",
        ]);
    }


    /**
     * Set whether loudness normalisation is on or not.
     *
     * @param bool $on Whether loudness should be on or not
     *
     * @return $this
     */
    public function setLoudness(bool $on): SpeakerInterface
    {
        $this->soap("RenderingControl", "SetLoudness", [
            "Channel"           =>  "Master",
            "DesiredLoudness"   =>  $on ? 1 : 0,
        ]);

        return $this;
    }
}
