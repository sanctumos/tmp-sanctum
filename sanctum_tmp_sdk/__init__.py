"""
Sanctum Temp Python SDK — upload + QR encode for tmp.sanctumos.org.
"""

from .client import DEFAULT_BASE, SanctumTmpClient, client_from_env
from .exceptions import (
    APIError,
    QrCapacityError,
    SanctumTmpError,
    ValidationError,
)

__version__ = "0.1.0"
__all__ = [
    "SanctumTmpClient",
    "client_from_env",
    "DEFAULT_BASE",
    "SanctumTmpError",
    "APIError",
    "ValidationError",
    "QrCapacityError",
    "__version__",
]
