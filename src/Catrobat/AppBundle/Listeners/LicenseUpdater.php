<?php

namespace Catrobat\AppBundle\Listeners;

use Catrobat\AppBundle\Events\ProgramBeforeInsertEvent;
use Catrobat\AppBundle\Services\ExtractedCatrobatFile;

class LicenseUpdater
{
    const MEDIALICENSE = "http://developer.catrobat.org/ccbysa_v4";
    const PROGRAMLICENSE = "http://developer.catrobat.org/agpl_v3";

    public function onProgramBeforeInsert(ProgramBeforeInsertEvent $event)
    {
        $this->update($event->getExtractedFile());
    }

    public function update(ExtractedCatrobatFile $file)
    {
        $program_xml_properties = $file->getProgramXmlProperties();
        $program_xml_properties->header->mediaLicense = self::MEDIALICENSE;
        $program_xml_properties->header->programLicense = self::PROGRAMLICENSE;
        $file->saveProgramXmlProperties();
    }
}
