<?php

namespace CodingSunshine\Ensemble\Console\Enums;

enum ExitCode: int
{
    case Success = 0;
    case ValidationError = 1;
    case GenerationError = 2;
    case InstallError = 3;
    case AiError = 4;
}
