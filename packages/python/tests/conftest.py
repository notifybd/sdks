import pytest

from notifybd import aio, client


@pytest.fixture(autouse=True)
def no_sleep(monkeypatch: pytest.MonkeyPatch) -> None:
    """Retries must not slow the suite down."""

    async def async_noop(_: float) -> None:
        return None

    monkeypatch.setattr(client, "_sleep", lambda _: None)
    monkeypatch.setattr(aio, "_sleep", async_noop)
