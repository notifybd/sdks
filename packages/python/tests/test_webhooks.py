import json
from pathlib import Path
from typing import Any, Dict

import pytest

from notifybd import SignatureVerificationError, construct_event

FIXTURE: Dict[str, Any] = json.loads(
    (Path(__file__).parents[3] / "spec" / "fixtures" / "webhook-signature.json").read_text()
)


@pytest.mark.parametrize("case", FIXTURE["cases"], ids=lambda c: c["name"])
def test_shared_vectors(case: Dict[str, Any]) -> None:
    def run() -> Any:
        return construct_event(
            case.get("rawBody", FIXTURE["rawBody"]),
            case["header"],
            FIXTURE["secret"],
            tolerance=FIXTURE["toleranceSeconds"],
            now=case["now"],
        )

    if case["valid"]:
        assert run().event_name == "message.delivered"
    else:
        with pytest.raises(SignatureVerificationError) as error:
            run()
        assert error.value.reason == case["reason"]


def test_accepts_bytes_body() -> None:
    case = FIXTURE["cases"][0]
    event = construct_event(
        FIXTURE["rawBody"].encode(), case["header"], FIXTURE["secret"], now=case["now"]
    )
    assert event.payload.status == "delivered"
    assert event.payload.reason is None


def test_rejects_missing_header() -> None:
    with pytest.raises(SignatureVerificationError):
        construct_event(FIXTURE["rawBody"], None, FIXTURE["secret"])
