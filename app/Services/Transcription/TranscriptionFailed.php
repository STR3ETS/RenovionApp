<?php

namespace App\Services\Transcription;

use RuntimeException;

/** De dienst meldt dat het transcriberen mislukt is (bijv. geen spraak, kapot bestand). */
class TranscriptionFailed extends RuntimeException {}
